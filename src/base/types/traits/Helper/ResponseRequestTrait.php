<?php

namespace PSFS\base\types\traits\Helper;

use PSFS\base\Request;
use PSFS\base\config\Config;

trait ResponseRequestTrait
{
    public static function isSecureRequest(): bool
    {
        if (Config::getParam('force.https', false)) {
            return true;
        }

        $request = Request::getInstance();
        $https = strtolower((string)$request->getServer('HTTPS', ''));
        if (!empty($https) && $https !== 'off') {
            return true;
        }

        $scheme = strtolower((string)$request->getServer('REQUEST_SCHEME', ''));
        if ($scheme === 'https') {
            return true;
        }

        $forwardedProto = strtolower((string)$request->getServer('HTTP_X_FORWARDED_PROTO', ''));
        return str_contains($forwardedProto, 'https');
    }
}
