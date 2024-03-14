<?php

namespace PSFS\base\types\helpers;

use PSFS\base\exception\ConfigException;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Template;
use PSFS\base\types\Api;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class GeneratorHelper
 * @package PSFS\base\types\helpers
 */
class GeneratorHelper
{
    /**
     * @param $dir
     */
    public static function deleteDir($dir)
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
                    syslog(LOG_INFO, $e->getMessage());
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
        if (!empty($dir)) {
            try {
                if (!is_dir($dir) && @mkdir($dir, 0775, true) === false) {
                    throw new \Exception(t('Can\'t create directory ') . $dir);
                }
            } catch (\Exception $e) {
                syslog(LOG_WARNING, $e->getMessage());
                if (!file_exists(dirname($dir))) {
                    throw new GeneratorException($e->getMessage() . $dir);
                }
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
    public static function extractClassFromNamespace($namespace)
    {
        $parts = preg_split('/(\\\|\\/)/', $namespace);
        return array_pop($parts);
    }

    /**
     * @param $namespace
     * @throws GeneratorException
     * @throws \ReflectionException
     */
    public static function checkCustomNamespaceApi($namespace)
    {
        if (!empty($namespace)) {
            if (class_exists($namespace)) {
                $reflector = new \ReflectionClass($namespace);
                if (!$reflector->isSubclassOf(Api::class)) {
                    throw new GeneratorException(t('La clase definida debe extender de PSFS\\\base\\\types\\\Api'), 501);
                } elseif (!$reflector->isAbstract()) {
                    throw new GeneratorException(t('La clase definida debe ser abstracta'), 501);
                }
            } else {
                throw new GeneratorException(t('La clase definida para extender la API no existe'), 501);
            }
        }
    }

    /**
     * @param string $path
     * @param OutputInterface|null $output
     * @param boolean $quiet
     * @throws GeneratorException
     */
    public static function createRoot($path = WEB_DIR, OutputInterface $output = null, $quiet = false)
    {

        if (null === $output) {
            $output = new ConsoleOutput();
        }

        GeneratorHelper::createDir($path);
        $paths = array("js", "css", "img", "media", "font");
        foreach ($paths as $htmlPath) {
            GeneratorHelper::createDir($path . DIRECTORY_SEPARATOR . $htmlPath);
        }

        // Generates the root needed files
        $files = [
            'index' => 'index.php',
            'browserconfig' => 'browserconfig.xml',
            'crossdomain' => 'crossdomain.xml',
            'humans' => 'humans.txt',
            'robots' => 'robots.txt',
            'docker' => '..' . DIRECTORY_SEPARATOR . 'docker-compose.yml',
        ];
        $verificable = ['humans', 'robots', 'docker'];
        $output->writeln('Start creating html files');
        foreach ($files as $template => $filename) {
            if (in_array($template, $verificable) && file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
                $output->writeln($filename . ' already exists');
                continue;
            }
            $text = Template::getInstance()->dump("generator/html/" . $template . '.html.twig');
            if (false === file_put_contents($path . DIRECTORY_SEPARATOR . $filename, $text)) {
                if (!$quiet) {
                    $output->writeln('Can\t create the file ' . $filename);
                }
            } else {
                if (!$quiet) {
                    $output->writeln($filename . ' created successfully');
                }
            }
        }

        //Export base locale translations
        if (!file_exists(BASE_DIR . DIRECTORY_SEPARATOR . 'locale')) {
            GeneratorHelper::createDir(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
            self::copyr(SOURCE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'locale', BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        }
    }

    /**
     * Method that copy resources recursively
     * @param string $dest
     * @param boolean $force
     * @param string $filenamePath
     * @param boolean $debug
     * @throws GeneratorException
     */
    public static function copyResources($dest, $force, $filenamePath, $debug)
    {
        if (file_exists($filenamePath)) {
            $destfolder = basename($filenamePath);
            if (!file_exists(WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder) || $debug || $force) {
                if (is_dir($filenamePath)) {
                    self::copyr($filenamePath, WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder);
                } else {
                    if (@copy($filenamePath, WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder) === FALSE) {
                        throw new ConfigException("Can't copy " . $filenamePath . " to " . WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder);
                    }
                }
            }
        }
    }

    /**
     * Method that copy a resource
     * @param string $src
     * @throws GeneratorException
     */
    public static function copyr($src, $dst)
    {
        $dir = opendir($src);
        self::createDir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::copyr($src . '/' . $file, $dst . '/' . $file);
                } elseif (@copy($src . '/' . $file, $dst . '/' . $file) === false) {
                    throw new ConfigException("Can't copy " . $src . " to " . $dst);
                }
            }
        }
        closedir($dir);
    }
}
