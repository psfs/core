<?php
namespace PSFS\base\types\helpers;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

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
        return @file_put_contents($path, $data);
    }

    /**
     * @param string $path
     * @return mixed
     */
    public static function readFile($path) {
        $data = false;
        if(file_exists($path)) {
            $data = @file_get_contents($path);
        }
        return $data;
    }

    /**
     * @param string $verb
     * @param string $slug
     * @param array $query
     * @return string
     */
    public static function generateHashFilename($verb, $slug, array $query = []) {
        return sha1(strtolower($verb) . ' ' . $slug . ' ' . strtolower(http_build_query($query)));
    }

    /**
     * @param array $action
     * @param array $query
     * @return string
     */
    public static function generateCachePath(array $action, array $query = []) {
        $class = GeneratorHelper::extractClassFromNamespace($action['class']);
        $filename = self::generateHashFilename($action['http'], $action['slug'], $query);
        $subPath = substr($filename, 0, 2) . DIRECTORY_SEPARATOR . substr($filename, 2, 2);
        return $action['module'] . DIRECTORY_SEPARATOR . $class . DIRECTORY_SEPARATOR . $action['method'] . DIRECTORY_SEPARATOR . $subPath . DIRECTORY_SEPARATOR;
    }

    /**
     * @param $path
     * @throws IOException
     */
    public static function deleteDir($path) {
        (new Filesystem())->remove($path);
    }
}