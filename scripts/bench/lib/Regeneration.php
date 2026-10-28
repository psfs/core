<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * Measures how long a module with many tables takes to regenerate, and where the time and the memory go.
 * Every measure runs in a fresh PHP process against an isolated sandbox and a temporary MySQL database.
 */
final class Regeneration
{
    /** initial: no tables yet; regen: database up to date; repeat: regen with generator.migration.skip_unchanged after one comparison */
    public const STATES = ['initial', 'regen', 'repeat'];

    private ?Sandbox $sandbox = null;
    private ?\PDO $pdo = null;

    /**
     * @param int[] $tables
     * @param string[] $states
     */
    public function __construct(private readonly array $tables, private readonly array $states, private readonly string $root)
    {
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function run(): array
    {
        $this->sandbox = new Sandbox($this->root, Options::fromArguments(['--scenarios=page', '--modules=1']));
        try {
            $this->sandbox->build();
            file_put_contents($this->sandbox->path() . '/regenerate.php', RegenerationScripts::REGENERATE);
            return $this->measureAll();
        } finally {
            $this->cleanup();
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function measureAll(): array
    {
        $results = [];
        $index = 0;
        foreach ($this->tables as $tables) {
            foreach ($this->states as $state) {
                $index++;
                fprintf(STDERR, "[tables=%d state=%s] ", $tables, $state);
                $result = ['tables' => $tables, 'state' => $state] + $this->measure($index, $tables, $state);
                fprintf(STDERR, "%.0f ms, %.0f MB\n", $result['wall_ms'], $result['peak_mb']);
                $results[] = $result;
            }
        }
        return $results;
    }

    /**
     * @return array<string,mixed>
     */
    private function measure(int $index, int $tables, string $state): array
    {
        $module = 'REGEN' . $index;
        $database = 'psfs_regen_' . bin2hex(random_bytes(4));
        $prepared = 'initial' === $state ? 'initial' : 'regen';
        try {
            $this->script('prepare', $prepared, $tables, $module, $database, 'repeat' === $state);
            $this->assertDatabaseState($database, $prepared, $tables);
            if ('repeat' === $state) {
                $this->script('measure', $prepared, $tables, $module, $database, true);
            }
            $output = $this->script('measure', $prepared, $tables, $module, $database, 'repeat' === $state);
        } finally {
            $this->pdo()->exec("DROP DATABASE IF EXISTS `$database`");
            Shell::run(sprintf('rm -rf %s/modules/%s %s/cache/*', escapeshellarg($this->sandbox->path()), $module, escapeshellarg($this->sandbox->path())));
        }
        $lines = explode("\n", trim($output));
        return json_decode((string)end($lines), true) ?: throw new \RuntimeException("No measure reported:\n" . $output);
    }

    private function script(string $step, string $state, int $tables, string $module, string $database, bool $skipUnchanged): string
    {
        return Shell::run(sprintf(
            'cd %s && PSFS_GENERATOR_PROFILE=true PSFS_GENERATOR_MIGRATION_SKIP_UNCHANGED=%s REGEN_TREE_FILE=%s php -d xdebug.mode=off regenerate.php %s %s %d %s %s',
            escapeshellarg($this->sandbox->path()),
            $skipUnchanged ? 'true' : 'false',
            escapeshellarg((string)getenv('REGEN_TREE_FILE')),
            $step,
            $state,
            $tables,
            $module,
            $database
        ));
    }

    /**
     * The regen state is only meaningful when the database already has the tables of the schema
     */
    private function assertDatabaseState(string $database, string $state, int $tables): void
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name LIKE ?');
        $statement->execute([$database, 'REGEN%\_ITEM%']);
        $found = (int)$statement->fetchColumn();
        $expected = 'regen' === $state ? $tables * 2 : 0;
        if ($found < $expected) {
            throw new \RuntimeException(sprintf('Expected at least %d tables in the database for state %s, found %d', $expected, $state, $found));
        }
    }

    private function pdo(): \PDO
    {
        return $this->pdo ??= new \PDO('mysql:host=db;port=3306;charset=utf8mb4', 'root', 'psfs', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    private function cleanup(): void
    {
        $this->sandbox?->remove();
    }

    /**
     * @param array<int,array<string,mixed>> $results
     */
    public static function report(array $results): string
    {
        $lines = ['| tables | state | wall s | peak MB | files | tree | phases (ms, +peak MB) x runs |', '|---|---|---|---|---|---|---|'];
        foreach ($results as $result) {
            $lines[] = sprintf(
                '| %d | %s | %.1f | %.0f | %d | %s | %s |',
                $result['tables'], $result['state'], $result['wall_ms'] / 1000, $result['peak_mb'], $result['files'], substr($result['tree'], 0, 8), self::phases($result['profile'])
            );
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string,array{ms:float,peak:int,runs:int}> $profile
     */
    private static function phases(array $profile): string
    {
        $parts = [];
        foreach ($profile as $phase => $data) {
            $parts[] = sprintf('%s %.0f (+%.0f) x%d', $phase, $data['ms'], $data['peak'] / 1048576, $data['runs']);
        }
        return implode(', ', $parts);
    }
}
