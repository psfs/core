<?php
namespace PSFS\base\types\helpers;

/**
 * Class ServiceHelper
 * @package PSFS\base\types\helpers
 */
class ServiceHelper {
    const TYPE_JSON = 0;
    const TYPE_MULTIPART = 1;
    const TYPE_HTTP = 2;

    public static function parseGetUrl() {

    }

    public static function parseRawData($type, $params) {
        switch($type) {
            default:
            case self::TYPE_HTTP:
                $parsedParams = http_build_query($params);
                break;
            case self::TYPE_JSON:
                $parsedParams = json_encode($params);
                break;
            case self::TYPE_MULTIPART:
                $parsedParams = $params;
                break;
        }
        return $parsedParams;
    }
}