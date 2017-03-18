<?php
namespace PSFS\base\types\helpers;

/**
 * Class FileHelper
 * @package PSFS\base\types\helpers
 */
class FileHelper {
    /**
     * @param mixed $data
     * @param string $path
     * @return int
     */
    public static function writeFile($path, $data) {
        return file_put_contents($path, $data);
    }

    /**
     * @param string $path
     * @return mixed|bool
     */
    public static function readFile($path) {
        $data = false;
        if(file_exists($path)) {
            $data = file_get_contents($path);
        }
        return $data;
    }
}