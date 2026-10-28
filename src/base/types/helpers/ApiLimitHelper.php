<?php

namespace PSFS\base\types\helpers;

/**
 * Page size limit of the generic API list endpoints.
 *
 * api.max.limit (default 1000) caps __limit, and __limit=-1 ("everything") returns that many rows instead of the
 * whole table. api.limit.unbounded=true restores the previous behaviour and is reported as deprecated.
 */
final class ApiLimitHelper
{
    public const DEFAULT_MAX = 1000;

    /**
     * @return array{0:int,1:bool} the limit to apply and whether the requested one was capped
     */
    public static function resolve(int $requested): array
    {
        $max = max(1, CompatHelper::int('api.max.limit', self::DEFAULT_MAX));
        $exceeds = -1 === $requested || $requested > $max;
        if (CompatHelper::flag('api.limit.unbounded', false)) {
            if ($exceeds) {
                CompatHelper::warnOnce(
                    'api.limit.unbounded',
                    'api.limit.unbounded=true allows API lists above api.max.limit (' . $max . '); this switch will be removed'
                );
            }
            return [$requested, false];
        }
        return $exceeds ? [$max, true] : [$requested, false];
    }
}
