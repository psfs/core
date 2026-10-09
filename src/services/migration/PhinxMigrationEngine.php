<?php

namespace PSFS\services\migration;

use Propel\Generator\Manager\MigrationManager;
use Propel\Generator\Model\Diff\DatabaseDiff;
use Closure;
use PSFS\base\Logger;

class PhinxMigrationEngine implements MigrationEngineInterface
{
    private readonly MigrationStatementNormalizer $statementNormalizer;

    /**
     * @param null|callable(string):bool $binaryChecker
     */
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly PhinxConfigFactory $configFactory,
        SqlStatementSplitter $splitter,
        private readonly ?Closure $binaryChecker = null,
        private readonly ?PropelDiffToPhinxMigrationGenerator $diffTranslator = null
    ) {
        $this->statementNormalizer = new MigrationStatementNormalizer($splitter);
    }

    public function getName(): string
    {
        return 'phinx';
    }

    public function isAvailable(): bool
    {
        $binary = $this->getBinaryPath();
        if (null !== $this->binaryChecker) {
            return (bool)call_user_func($this->binaryChecker, $binary);
        }
        return is_file($binary) || is_executable($binary);
    }

    public function migrate(MigrationExecutionContext $context): MigrationExecutionResult
    {
        return $this->executePhinx('migrate', $context);
    }

    public function rollback(MigrationExecutionContext $context): MigrationExecutionResult
    {
        return $this->executePhinx('rollback', $context);
    }

    public function status(MigrationExecutionContext $context): MigrationExecutionResult
    {
        return $this->executePhinx('status', $context);
    }

    public function seed(MigrationExecutionContext $context): MigrationExecutionResult
    {
        $config = $this->configFactory->createForModule($context->getModule(), $context->getMigrationDir());
        $seedDir = $config['paths']['seeds'] ?? ($context->getMigrationDir() . DIRECTORY_SEPARATOR . 'Seeds');
        $seeders = is_dir($seedDir) ? glob(rtrim($seedDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') : false;
        if (!is_array($seeders) || $seeders === []) {
            return MigrationExecutionResult::failure(
                $this->getName(),
                sprintf('No Phinx seeders found in %s', $seedDir),
                1
            );
        }

        return $this->executePhinx('seed:run', $context);
    }

    public function generateFromDiff(
        string $module,
        array $migrationsUp,
        array $migrationsDown,
        string $migrationDir,
        int $timestamp,
        ?MigrationManager $manager = null
    ): MigrationExecutionResult {
        $moduleClass = $this->normalizeModuleClassName($module);
        do {
            $version = date('YmdHis', $timestamp);
            $className = sprintf('Auto%sSchemaDiff%s', $moduleClass, $version);
            $fileName = sprintf('%s_%s.php', $version, $className);
            $target = $migrationDir . DIRECTORY_SEPARATOR . $fileName;
            if (file_exists($target)) {
                ++$timestamp;
            }
        } while (file_exists($target));

        $structuredDiffs = $this->containsDatabaseDiff($migrationsUp) || $this->containsDatabaseDiff($migrationsDown);
        if ($structuredDiffs) {
            [$upStatements, $downStatements] = $this->translateDatabaseDiffs($migrationsUp, $migrationsDown);
            $content = $this->buildDeclarativeMigrationClass($className, $upStatements, $downStatements);
        } else {
            $upStatements = $this->statementNormalizer->normalize($migrationsUp);
            $downStatements = $this->statementNormalizer->normalize($migrationsDown);
            $content = $this->buildMigrationClass($className, $upStatements, $downStatements);
        }
        file_put_contents($target, $content);

        return MigrationExecutionResult::success($this->getName(), sprintf('Generated phinx migration: %s', $target));
    }

    private function containsDatabaseDiff(array $diffs): bool
    {
        foreach ($diffs as $diff) {
            if ($diff instanceof DatabaseDiff) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function translateDatabaseDiffs(array $migrationsUp, array $migrationsDown): array
    {
        if (count($migrationsUp) > 1 || count($migrationsDown) > 1) {
            throw new \InvalidArgumentException(
                'Cannot generate Phinx migration for multiple datasources: the module runtime config exposes one Phinx environment'
            );
        }

        $upKey = array_key_first($migrationsUp);
        $downKey = array_key_first($migrationsDown);
        if (null === $upKey || $upKey !== $downKey
            || !$migrationsUp[$upKey] instanceof DatabaseDiff
            || !$migrationsDown[$downKey] instanceof DatabaseDiff) {
            throw new \InvalidArgumentException(
                'Declarative Phinx migrations require matching Propel DatabaseDiff objects for up and down'
            );
        }

        $translator = $this->diffTranslator ?? new PropelDiffToPhinxMigrationGenerator();
        return [
            $translator->translate($migrationsUp[$upKey])['up'],
            $translator->translate($migrationsDown[$downKey])['up'],
        ];
    }

    /**
     * @param list<string> $up
     * @param list<string> $down
     */
    private function buildDeclarativeMigrationClass(string $className, array $up, array $down): string
    {
        $render = static fn(array $statements): string => implode("\n", array_map(
            static fn(string $statement): string => '        ' . $statement,
            $statements
        ));

        return <<<PHP
<?php

declare(strict_types=1);

use Phinx\\Migration\\AbstractMigration;

final class {$className} extends AbstractMigration
{
    public function up(): void
    {
{$render($up)}
    }

    public function down(): void
    {
{$render($down)}
    }
}
PHP;
    }

    /**
     * @param array<int, string> $up
     * @param array<int, string> $down
     */
    private function buildMigrationClass(string $className, array $up, array $down): string
    {
        $export = static function (array $statements): string {
            $encoded = array_map(static function (string $stmt): string {
                if (str_contains($stmt, "'")) {
                    return '"' . addcslashes($stmt, "\\\"") . '"';
                }

                return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $stmt) . "'";
            }, $statements);
            return '[' . implode(', ', $encoded) . ']';
        };

        return <<<PHP
<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class {$className} extends AbstractMigration
{
    public function up(): void
    {
        foreach ({$export($up)} as \$sql) {
            \$this->execute(\$sql);
        }
    }

    public function down(): void
    {
        foreach ({$export($down)} as \$sql) {
            \$this->execute(\$sql);
        }
    }
}
PHP;
    }

    private function executePhinx(string $subCommand, MigrationExecutionContext $context): MigrationExecutionResult
    {
        $runtimeConfig = $this->persistRuntimeConfig($context);
        $environment = $this->configFactory->createForModule($context->getModule(), $context->getMigrationDir())['environments']['default_environment'];
        $simulate = $context->isSimulate() ? ' --dry-run' : '';
        $target = null !== $context->getTargetVersion() ? ' --target=' . $context->getTargetVersion() : '';

        $command = sprintf(
            '%s %s -c %s -e %s%s%s',
            escapeshellarg($this->getBinaryPath()),
            $subCommand,
            escapeshellarg($runtimeConfig),
            escapeshellarg((string)$environment),
            $simulate,
            $target
        );

        $result = $this->runner->run($command);
        if (!@unlink($runtimeConfig)) {
            Logger::log('[PhinxMigrationEngine] Unable to remove runtime config: ' . $runtimeConfig, LOG_WARNING);
        }

        if (0 === $result['exit_code']) {
            return MigrationExecutionResult::success($this->getName(), $result['output'], $command);
        }

        return MigrationExecutionResult::failure($this->getName(), $result['output'], $result['exit_code'], $command);
    }

    private function persistRuntimeConfig(MigrationExecutionContext $context): string
    {
        $config = $this->configFactory->createForModule($context->getModule(), $context->getMigrationDir());
        $runtimeDir = CACHE_DIR . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . 'phinx';
        if (!is_dir($runtimeDir)) {
            mkdir($runtimeDir, 0777, true);
        }
        $path = $runtimeDir . DIRECTORY_SEPARATOR . $this->buildRuntimeConfigFilename($context->getModule());
        $payload = "<?php\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($path, $payload);

        return $path;
    }

    private function getBinaryPath(): string
    {
        return VENDOR_DIR . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phinx';
    }

    private function normalizeModuleClassName(string $module): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]/', '', $module);
        return '' !== $normalized ? ucfirst($normalized) : 'Module';
    }

    private function normalizeModuleRuntimeKey(string $module): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower($module));
        $normalized = trim((string)$normalized, '_');
        return '' !== $normalized ? $normalized : 'module';
    }

    private function buildRuntimeConfigFilename(string $module): string
    {
        $base = $this->normalizeModuleRuntimeKey($module);
        $pid = getmypid();
        try {
            $random = bin2hex(random_bytes(4));
        } catch (\Throwable) {
            $random = uniqid('', true);
        }

        return sprintf('%s_%s_%s.php', $base, $pid ?: 'pid', $random);
    }
}
