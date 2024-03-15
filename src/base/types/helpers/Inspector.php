<?php

namespace PSFS\base\types\helpers;

/**
 * Class Inspector
 * @package PSFS\base\types\helpers
 */
class Inspector
{

    const SCOPE_PROFILE = 'PROFILE';
    const SCOPE_DEBUG = 'DEBUG';

    protected static array $profiling = [];
    protected static array $debug = [];

    /**
     * @param string|null $name
     * @return array
     */
    public static function collect(string $name = null): array
    {
        return [
            'ts' => microtime(true),
            'mem' => memory_get_usage(),
            'files' => count(get_required_files()),
            'name' => $name ?: static::class,
        ];
    }

    /**
     * @param string|null $name
     * @param string $scope
     */
    public static function stats(string $name = null, string $scope = self::SCOPE_PROFILE): void
    {
        self::$debug[] = self::collect($name);
        if ($scope === self::SCOPE_PROFILE) {
            self::$profiling[] = self::collect($name);
        }
    }

    /**
     * @param array $stats
     * @param float $timestamp
     * @param int $mem
     * @param int $files
     * @return array
     */
    protected static function calculateStats(array $stats, float $timestamp, int $mem = 0, int $files = 0): array
    {
        return [
            'ts' => round($stats['ts'] - $timestamp, 4),
            'mem' => round(($stats['mem'] - $mem) / 1024 / 1024, 4),
            'files' => $stats['files'] - $files,
            'name' => $stats['name'],
        ];
    }

    /**
     * @param string $scope
     * @return array
     */
    protected static function processStats(string $scope = self::SCOPE_PROFILE): array
    {
        self::stats('Profiling collect start');
        $timestamp = defined('PSFS_START_TS') ? PSFS_START_TS : 0;
        $mem = defined('PSFS_START_MEM') ? PSFS_START_MEM : 0;
        $files = 0;
        $scopeSelected = $scope === self::SCOPE_DEBUG ? self::$debug : self::$profiling;
        foreach ($scopeSelected as &$value) {
            $value = self::calculateStats($value, $timestamp, $mem, $files);
        }
        $scopeSelected[] = self::calculateStats(self::collect('Profiling collect ends'), $timestamp, $mem, $files);
        return $scopeSelected;
    }

    /**
     * @param string $scope
     * @return array
     */
    public static function getStats(string $scope = self::SCOPE_PROFILE): array
    {
        return self::processStats($scope);
    }
}
