<?php

namespace PSFS\base\types\helpers;

use PSFS\base\Logger;

/**
 * Time and peak memory of every phase of the module generation, to see where a regeneration spends its resources.
 * It is off unless generator.profile (PSFS_GENERATOR_PROFILE) is enabled, and then the summary is logged at the end.
 */
class GeneratorProfile
{
    /** @var array<string, array{ms: float, peak: int, runs: int}> */
    private static array $phases = [];
    /** @var array<string, array{0: float, 1: int}> */
    private static array $running = [];

    public static function isEnabled(): bool
    {
        return in_array(strtolower((string)LogHelper::setting('generator.profile', 'false')), ['1', 'true', 'yes'], true);
    }

    public static function start(string $phase): void
    {
        if (!self::isEnabled()) {
            return;
        }
        memory_reset_peak_usage();
        self::$running[$phase] = [microtime(true), memory_get_usage()];
    }

    public static function stop(string $phase): void
    {
        if (!isset(self::$running[$phase])) {
            return;
        }
        [$began, $memory] = self::$running[$phase];
        unset(self::$running[$phase]);
        $known = self::$phases[$phase] ?? ['ms' => 0.0, 'peak' => 0, 'runs' => 0];
        self::$phases[$phase] = [
            'ms' => $known['ms'] + (microtime(true) - $began) * 1000,
            'peak' => max($known['peak'], memory_get_peak_usage() - $memory),
            'runs' => $known['runs'] + 1,
        ];
    }

    /**
     * @return array<string, array{ms: float, peak: int, runs: int}> milliseconds, extra peak bytes and runs per phase
     */
    public static function report(): array
    {
        return self::$phases;
    }

    public static function reset(): void
    {
        self::$phases = [];
        self::$running = [];
    }

    /**
     * @return string one line per phase: milliseconds, extra peak memory and number of runs
     */
    public static function summary(): string
    {
        $lines = [];
        foreach (self::$phases as $phase => $data) {
            $lines[] = sprintf('%s: %.1f ms, +%.1f MB peak, %d run(s)', $phase, $data['ms'], $data['peak'] / 1048576, $data['runs']);
        }
        return implode(' | ', $lines);
    }

    /**
     * Logs the summary when the profile is enabled and anything has been measured
     */
    public static function logSummary(): void
    {
        if (!self::isEnabled() || [] === self::$phases) {
            return;
        }
        Logger::log('[GeneratorProfile] ' . self::summary(), LOG_INFO);
    }
}
