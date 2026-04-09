<?php

namespace PSFS\base\types\helpers;

use PSFS\base\Logger;
use PSFS\base\exception\RequestTerminationException;
use PSFS\base\runtime\RuntimeMode;

class CorsLifecycleHelper
{
    public static function apply(string $allowedOrigin, bool $isOptionsRequest, array $allowHeaders): void
    {
        self::emitHeaders($allowedOrigin, $allowHeaders);
        if ($isOptionsRequest) {
            self::finalizePreflight();
        }
    }

    private static function emitHeaders(string $allowedOrigin, array $allowHeaders): void
    {
        if (headers_sent()) {
            return;
        }

        ResponseHelper::setHeader('Access-Control-Allow-Credentials: true');
        ResponseHelper::setHeader('Access-Control-Allow-Origin: ' . $allowedOrigin);
        ResponseHelper::setHeader('Vary: Origin');
        ResponseHelper::setHeader('Access-Control-Allow-Methods: GET, POST, DELETE, PUT, PATCH, OPTIONS, HEAD');
        ResponseHelper::setHeader('Access-Control-Allow-Headers: ' . implode(', ', $allowHeaders));
    }

    private static function finalizePreflight(): void
    {
        Logger::log('Returning OPTIONS header confirmation for CORS pre flight requests', LOG_DEBUG);
        ResponseHelper::setStatusHeader('HTTP/1.1 204 No Content');
        if (RuntimeMode::isLongRunningServer()) {
            throw new RequestTerminationException('CORS preflight request finalized');
        }
        exit();
    }
}
