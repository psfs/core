<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * The requests the benchmark sends and what a valid response looks like
 */
final class Scenarios
{
    private const ALL = [
        'page' => ['module' => 'BENCHPAGE', 'path' => '/BENCHPAGE', 'marker' => 'BENCH-PAGE-OK'],
        'api-get' => ['module' => 'CLIENT', 'path' => '/client/api/test/1', 'marker' => '"success":true'],
        'api-list' => ['module' => 'CLIENT', 'path' => '/client/api/test?__limit=20&__page=1', 'marker' => '"success":true'],
    ];

    public static function exists(string $name): bool
    {
        return isset(self::ALL[$name]);
    }

    /**
     * @return array{module:string,path:string,marker:string}
     */
    public static function get(string $name): array
    {
        return self::ALL[$name];
    }

    public static function isApi(string $name): bool
    {
        return str_starts_with($name, 'api');
    }
}
