<?php

namespace PSFS\services\migration;

use PSFS\base\types\helpers\FileHelper;
use PSFS\base\types\helpers\LogHelper;

/**
 * Remembers that the database matched the schema of a module, so regenerating it again without touching the schema
 * or the migrations can skip the comparison. Comparing means reading every table of the database, which is slow when
 * the database is remote or shared. It is off unless generator.migration.skip_unchanged (or the environment variable
 * PSFS_GENERATOR_MIGRATION_SKIP_UNCHANGED) is enabled, because a database changed by hand is not noticed meanwhile.
 * The state lives in the cache directory: it describes one environment, never the project.
 */
class MigrationSyncState
{
    public function __construct(private readonly string $module, private readonly ?string $directory = null)
    {
    }

    public static function enabled(): bool
    {
        return in_array(strtolower((string)LogHelper::setting('generator.migration.skip_unchanged', 'false')), ['1', 'true', 'yes'], true);
    }

    /**
     * Identifies the schema and the migrations that were present when the database was compared
     */
    public static function signature(string $schemaDirectory, string $migrationDirectory): string
    {
        $parts = [];
        foreach (self::files($schemaDirectory, '*schema.xml') as $file) {
            $parts[] = basename($file) . ':' . sha1_file($file);
        }
        foreach (self::files($migrationDirectory, '*') as $file) {
            $parts[] = basename($file);
        }
        return sha1(implode('|', $parts));
    }

    public function matches(string $signature): bool
    {
        $state = json_decode((string)FileHelper::readFile($this->file()), true);
        return is_array($state) && ($state['signature'] ?? null) === $signature;
    }

    public function remember(string $signature): void
    {
        FileHelper::writeFileAtomic($this->file(), (string)json_encode(['signature' => $signature, 'at' => date(DATE_ATOM)]));
    }

    public function forget(): void
    {
        FileHelper::deleteFile($this->file());
    }

    public function file(): string
    {
        $slug = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($this->module));
        return ($this->directory ?? CACHE_DIR) . DIRECTORY_SEPARATOR . 'generator-sync-' . $slug . '.json';
    }

    /**
     * @return string[] sorted files of the directory that match the pattern
     */
    private static function files(string $directory, string $pattern): array
    {
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pattern) ?: [];
        $files = array_filter($files, 'is_file');
        sort($files);
        return $files;
    }
}
