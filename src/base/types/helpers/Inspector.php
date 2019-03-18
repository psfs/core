<?php
namespace PSFS\base\types\helpers;

/**
 * Class Inspector
 * @package PSFS\base\types\helpers
 */
class Inspector {

    protected static $profiling = [];

    /**
     * @param string $name
     * @return array
     */
    public static function collect($name = null) {
        return [
            'ts' => microtime(true),
            'mem' => memory_get_usage(),
            'files' => count(get_required_files()),
            'name' => $name ?: static::class,
        ];
    }

    /**
     * @param string $name
     */
    public static function stats($name = null) {
        self::$profiling[] = self::collect($name);
    }

    /**
     * @param array $stats
     * @param float $timestamp
     * @param int $mem
     * @param int $files
     * @return array
     */
    protected static function calculateStats(array $stats, $timestamp, $mem = 0, $files = 0) {
        return [
            'ts' => round($stats['ts'] - $timestamp, 4),
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
        $timestamp = defined('PSFS_START_TS') ? PSFS_START_TS : 0;
        $mem = defined('PSFS_START_MEM') ? PSFS_START_MEM : 0;
        $files = 0;
        foreach(self::$profiling as &$value) {
            $value = self::calculateStats($value, $timestamp, $mem, $files);
        }
        self::$profiling[] = self::calculateStats(self::collect('Profiling collect ends'), $timestamp, $mem, $files);
        return self::$profiling;
    }

    /**
     * @return array
     */
    public static function getStats() {
        return self::processStats();
    }
}