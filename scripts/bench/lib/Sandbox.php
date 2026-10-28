<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * An isolated copy of the framework under /tmp, with its own modules, configuration, cache and logs
 */
final class Sandbox
{
    private const PREFIX = '/tmp/psfs-bench-';
    private const DIRECTORIES = ['modules', 'parked', 'config', 'cache', 'logs', 'sessions'];
    private const TEST_SUPPORT = ['tests_api/support', 'tests/examples/generator', 'tests/fixtures/sql'];

    private readonly string $path;
    private string $database = '';
    private int $modules = -1;

    /**
     * @param string|null $path where to build it; by default a new directory under /tmp. A path of your own lets other
     *                          containers that mount the project see it (nginx and php-fpm need the same files)
     */
    public function __construct(private readonly string $root, private readonly Options $options, private readonly ?string $explicitPath = null)
    {
        $this->path = $explicitPath ?? self::PREFIX . bin2hex(random_bytes(4));
    }

    public function path(): string
    {
        return $this->path;
    }

    public function database(): string
    {
        return $this->database;
    }

    public function build(): void
    {
        fprintf(STDERR, "building sandbox %s ...\n", $this->path);
        $this->createDirectories();
        $this->copySources();
        $this->writeInitialConfig();
        file_put_contents($this->path . '/bench-prepend.php', SandboxScripts::PREPEND);
        file_put_contents($this->path . '/prep.php', SandboxScripts::PREP);
        $this->generateModules();
    }

    /**
     * Makes exactly $total modules visible: the scenario modules plus as many generated dummies as needed
     */
    public function useModules(int $total): void
    {
        if ($this->modules === $total) {
            return;
        }
        $wanted = $total - count($this->options->baseModules());
        $dummies = $this->dummyModules();
        sort($dummies, SORT_NATURAL);
        foreach ($dummies as $index => $directory) {
            $this->move($directory, $index < $wanted ? 'modules' : 'parked');
        }
        Shell::run(sprintf('cd %s && php -d xdebug.mode=off src/bin/psfs psfs:deploy:project', escapeshellarg($this->path)));
        $this->modules = $total;
    }

    /**
     * @param array<string,mixed> $overrides
     */
    public function writeConfig(array $overrides): void
    {
        $file = $this->path . '/config/config.json';
        $config = json_decode((string)file_get_contents($file), true) ?: [];
        file_put_contents($file, json_encode(array_merge($config, $overrides), JSON_PRETTY_PRINT));
    }

    /**
     * A cold start: empty caches and sessions
     */
    public function resetCaches(): void
    {
        $path = escapeshellarg($this->path);
        Shell::run(sprintf('rm -rf %s/cache/* %s/sessions/*', $path, $path));
    }

    public function remove(): void
    {
        if (null !== $this->explicitPath || str_starts_with($this->path, self::PREFIX)) {
            exec('rm -rf ' . escapeshellarg($this->path));
        }
    }

    private function createDirectories(): void
    {
        foreach (self::DIRECTORIES as $directory) {
            mkdir($this->path . '/' . $directory, 0775, true);
        }
    }

    private function copySources(): void
    {
        $root = escapeshellarg($this->root);
        $path = escapeshellarg($this->path);
        Shell::run(sprintf('cd %s && tar -cf - --exclude=src/public src composer.json | tar -xf - -C %s', $root, $path));
        Shell::run(sprintf('cp -a %s/vendor %s/vendor', $root, $path));
        if (!$this->options->needsApi()) {
            return;
        }
        foreach (self::TEST_SUPPORT as $directory) {
            mkdir($this->path . '/' . dirname($directory), 0775, true);
            Shell::run(sprintf('cp -a %s/%s %s/%s', $root, $directory, $path, $directory));
        }
    }

    /**
     * Without every required key PSFS stays in installer mode and answers each request with the admin setup
     */
    private function writeInitialConfig(): void
    {
        file_put_contents($this->path . '/config/config.json', json_encode([
            'debug' => false, 'cache.var' => 'bench', 'platform.name' => 'PSFSBench',
            'db.host' => 'db', 'db.port' => '3306', 'db.name' => 'psfs', 'db.user' => 'psfs', 'db.password' => 'psfs',
            'home.action' => 'admin', 'default.language' => 'en_US',
        ]));
    }

    private function generateModules(): void
    {
        $dummies = max(array_map('intval', $this->options->list('modules'))) - count($this->options->baseModules());
        $output = Shell::run(sprintf(
            'cd %s && php -d xdebug.mode=off prep.php %d %d %d',
            escapeshellarg($this->path),
            $this->options->needsPage() ? 1 : 0,
            $this->options->needsApi() ? 1 : 0,
            $dummies
        ));
        $lines = explode("\n", trim($output));
        $report = json_decode((string)end($lines), true);
        $this->database = (string)($report['db'] ?? '');
        if ($this->options->needsApi() && '' === $this->database) {
            throw new \RuntimeException("Sandbox preparation did not report a database:\n" . $output);
        }
        fprintf(STDERR, "sandbox ready (%d generated dummy modules%s)\n", $dummies, '' === $this->database ? '' : ', db ' . $this->database);
    }

    /**
     * @return string[] every generated dummy module, whether it is visible or parked
     */
    private function dummyModules(): array
    {
        // GLOB_BRACE is not available on every platform, so each directory is listed on its own
        $visible = glob($this->path . '/modules/BENCHDUMMY*', GLOB_ONLYDIR) ?: [];
        $parked = glob($this->path . '/parked/BENCHDUMMY*', GLOB_ONLYDIR) ?: [];
        return array_merge($visible, $parked);
    }

    private function move(string $directory, string $destination): void
    {
        $target = $this->path . '/' . $destination . '/' . basename($directory);
        if ($directory !== $target) {
            rename($directory, $target);
        }
    }
}
