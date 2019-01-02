<?php
namespace PSFS\base\types\helpers;

use PSFS\base\exception\GeneratorException;
use PSFS\base\Logger;

/**
 * Class GeneratorHelper
 * @package PSFS\base\types\helpers
 */
class GeneratorHelper
{
    /**
     * @param $dir
     */
    private static function deleteDir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        self::deleteDir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    /**
     * Method that remove all data in the document root path
     */
    public static function clearDocumentRoot()
    {
        $rootDirs = array("css", "js", "media", "font");
        foreach ($rootDirs as $dir) {
            if (file_exists(WEB_DIR . DIRECTORY_SEPARATOR . $dir)) {
                try {
                    self::deleteDir(WEB_DIR . DIRECTORY_SEPARATOR . $dir);
                } catch (\Exception $e) {
                    Logger::log($e->getMessage());
                }
            }
        }
    }

    /**
     * Method that creates any parametrized path
     * @param string $dir
     * @throws GeneratorException
     */
    public static function createDir($dir)
    {
        try {
            if (!is_dir($dir) && @mkdir($dir, 0775, true) === false) {
                throw new \Exception(t('Can\'t create directory ') . $dir);
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_WARNING);
            if (!file_exists(dirname($dir))) {
                throw new GeneratorException($e->getMessage() . $dir);
            }
        }
    }

    /**
     * Method that returns the templates path
     * @return string
     */
    public static function getTemplatePath()
    {
        $path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
        return realpath($path);
    }

    /**
     * @param $namespace
     * @return string
     */
    public static function extractClassFromNamespace($namespace) {
        $parts = preg_split('/(\\\|\\/)/', $namespace);
        return array_pop($parts);
    }

    /**
     * @param $namespace
     * @throws GeneratorException
     */
    public static function checkCustomNamespaceApi($namespace) {
        if(!empty($namespace)) {
            if(class_exists($namespace)) {
                $reflector = new \ReflectionClass($namespace);
                if(!$reflector->isSubclassOf(\PSFS\base\types\Api::class)) {
                    throw new GeneratorException(t('La clase definida debe extender de PSFS\\\base\\\types\\\Api'), 501);
                } elseif(!$reflector->isAbstract()) {
                    throw new GeneratorException(t('La clase definida debe ser abstracta'), 501);
                }
            } else {
                throw new GeneratorException(t('La clase definida para extender la API no existe'), 501);
            }
        }
    }
}