<?php

declare(strict_types=1);

namespace Psfs\Bench;

final class Stats
{
    /**
     * @param float[]|int[] $sorted values in ascending order
     */
    public static function percentile(array $sorted, int $percent): float
    {
        $index = (int)ceil(($percent / 100) * count($sorted)) - 1;
        return (float)$sorted[max(0, min(count($sorted) - 1, $index))];
    }

    /**
     * @param float[]|int[] $values
     */
    public static function median(array $values): float|int
    {
        sort($values);
        return $values[intdiv(count($values), 2)];
    }

    /**
     * Summarises what the server recorded for every request (see SandboxScripts::PREPEND)
     *
     * @return array{php_p50:float,peak_mb:float,files:int,opcache:bool}
     */
    public static function fromServerLog(string $file): array
    {
        $rows = array_values(array_filter(explode("\n", (string)file_get_contents($file)), 'strlen'));
        if ([] === $rows) {
            throw new \RuntimeException('the server reported no statistics');
        }
        $peak = $files = $php = [];
        $opcache = false;
        foreach ($rows as $row) {
            [$peakBytes, $fileCount, $milliseconds, $opcacheFlag] = explode("\t", $row);
            $peak[] = (int)$peakBytes;
            $files[] = (int)$fileCount;
            $php[] = (float)$milliseconds;
            $opcache = '1' === $opcacheFlag;
        }
        sort($php);
        sort($peak);
        sort($files);
        return [
            'php_p50' => self::percentile($php, 50),
            'peak_mb' => self::percentile($peak, 50) / 1048576,
            'files' => (int)self::percentile($files, 50),
            'opcache' => $opcache,
        ];
    }
}
