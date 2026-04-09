<?php

namespace PSFS\services\migration;

use Propel\Generator\Manager\MigrationManager;
use Closure;

class PhinxMigrationEngine implements MigrationEngineInterface
{
    /**
     * @param null|callable(string):bool $binaryChecker
     */
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly PhinxConfigFactory $configFactory,
        private readonly SqlStatementSplitter $splitter,
    private readonly ?Closure $binaryChecker = null
    ) {
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

    public function generateFromDiff(
        string $module,
        array $migrationsUp,
        array $migrationsDown,
        string $migrationDir,
        int $timestamp,
        ?MigrationManager $manager = null
    ): MigrationExecutionResult {
        $moduleClass = $this->normalizeModuleClassName($module);
        $className = sprintf('Auto%sSchemaDiff', $moduleClass);
        $fileName = sprintf('%s_%s.php', date('YmdHis', $timestamp), $className);

        $upStatements = $this->normalizeStatements($migrationsUp);
        $downStatements = $this->normalizeStatements($migrationsDown);

        $content = $this->buildMigrationClass($className, $upStatements, $downStatements);
        $target = $migrationDir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($target, $content);

        return MigrationExecutionResult::success($this->getName(), sprintf('Generated phinx migration: %s', $target));
    }

    /**
     * @param array<string, mixed> $migrationSql
     * @return array<int, string>
     */
    private function normalizeStatements(array $migrationSql): array
    {
        return array_values(iterator_to_array($this->iterateNormalizedStatements($migrationSql), false));
    }

    /**
     * @param array<string, mixed> $migrationSql
     * @return \Generator<int, string>
     */
    private function iterateNormalizedStatements(array $migrationSql): \Generator
    {
        foreach ($migrationSql as $sql) {
            if (is_array($sql)) {
                foreach ($sql as $raw) {
                    if (is_string($raw)) {
                        yield from $this->splitAndNormalize($raw);
                    }
                }
                continue;
            }
            if (is_string($sql)) {
                yield from $this->splitAndNormalize($sql);
            }
        }
    }

    /**
     * @return \Generator<int, string>
     */
    private function splitAndNormalize(string $sql): \Generator
    {
        foreach ($this->splitter->split($sql) as $statement) {
            $statement = trim($statement);
            if ('' !== $statement) {
                yield $statement;
            }
        }
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

        $command = sprintf(
            '%s %s -c %s -e %s%s',
            escapeshellarg($this->getBinaryPath()),
            $subCommand,
            escapeshellarg($runtimeConfig),
            escapeshellarg((string)$environment),
            $simulate
        );

        $result = $this->runner->run($command);
        @unlink($runtimeConfig);

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
