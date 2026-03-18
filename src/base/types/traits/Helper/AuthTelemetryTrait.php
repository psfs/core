<?php

namespace PSFS\base\types\traits\Helper;

use PSFS\base\Logger;

trait AuthTelemetryTrait
{
    private static array $legacyFallbackTelemetry = [];
    private static array $invalidInputTelemetry = [];

    /**
     * @return array<string,bool>
 */
    public static function getLegacyFallbackTelemetry(): array
    {
        return self::$legacyFallbackTelemetry;
    }

    public static function resetLegacyFallbackTelemetry(): void
    {
        self::$legacyFallbackTelemetry = [];
        self::$invalidInputTelemetry = [];
    }

    private static function logLegacyFallbackUsage(string $context): void
    {
        if (array_key_exists($context, self::$legacyFallbackTelemetry)) {
            return;
        }
        self::$legacyFallbackTelemetry[$context] = true;
        Logger::log('[LegacyFallback] ' . $context, LOG_NOTICE);
    }

    private static function logInvalidAuthInput(string $context): void
    {
        if (array_key_exists($context, self::$invalidInputTelemetry)) {
            return;
        }
        self::$invalidInputTelemetry[$context] = true;
        Logger::log('[AuthInvalid] ' . $context, LOG_WARNING);
    }
}
