<?php
namespace PSFS\base\types\helpers;

class Inspector {

    protected static $profiling = [];

    public static function collect($name = null) {
        return [
            'ts' => microtime(true),
            'mem' => memory_get_usage(),
            'files' => count(get_required_files()),
            'name' => $name ?: static::class,
        ];
    }

    public static function stats($name = null) {
        self::$profiling[] = self::collect($name);
    }

    /**
     * @param array $stats
     * @param float $ts
     * @param int $mem
     * @param int $files
     * @return array
     */
    protected static function calculateStats(array $stats, $ts, $mem = 0, $files = 0) {
        return [
            'ts' => round($stats['ts'] - $ts, 4),
            'mem' => round(($stats['mem'] - $mem) / 1024 / 1024, 4),
            'files' => $stats['files'] - $files,
            'name' => $stats['name'],
        ];
    }

    /**
     * @return array
     */
    protected static function processStats() {
        self::stats('Profiling collect start');
        $ts = defined('PSFS_START_TS') ? PSFS_START_TS : 0;
        $mem = defined('PSFS_START_MEM') ? PSFS_START_MEM : 0;
        $files = 0;
        foreach(self::$profiling as $key => &$value) {
            $value = self::calculateStats($value, $ts, $mem, $files);
        }
        self::$profiling[] = self::calculateStats(self::collect('Profiling collect ends'), $ts, $mem, $files);
        return self::$profiling;
    }

    /**
     * @return array
     */
    public static function getStats() {
        return self::processStats();
    }
}