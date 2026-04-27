<?php

namespace PSFS\base\types\helpers;

use Exception;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Template;
use PSFS\base\types\Api;
use ReflectionClass;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package PSFS\base\types\helpers
 */
class GeneratorHelper
{
    /**
     * @param $dir
     */
    public static function deleteDir($dir): void
    {
        if (is_string($dir)) {
            FilesystemTreeHelper::deleteDir($dir);
        }
    }


    public static function clearDocumentRoot(): void
    {
        $rootDirs = array("css", "js", "media", "font");
        foreach ($rootDirs as $dir) {
            $target = WEB_DIR . DIRECTORY_SEPARATOR . $dir;
            $realWebDir = realpath(WEB_DIR);
            $realTarget = realpath($target);
            if (
                file_exists($target)
                && false !== $realWebDir
                && false !== $realTarget
                && str_starts_with($realTarget, $realWebDir . DIRECTORY_SEPARATOR)
            ) {
                try {
                    self::deleteDir($target);
                } catch (\Exception $e) {
                    syslog(LOG_INFO, $e->getMessage());
                }
            }
        }
    }

    /**
     * @param string $dir
     * @throws GeneratorException
     */
    public static function createDir($dir): void
    {
        if (!empty($dir)) {
            try {
                if (!is_dir($dir) && @mkdir($dir, 0775, true) === false) {
                    throw new Exception(t('Can\'t create directory ') . $dir);
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
     * @return string
     */
    public static function getTemplatePath(): string
    {
        $path = __DIR__
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'templates';
        $resolvedPath = realpath($path);
        $finalPath = is_string($resolvedPath) && $resolvedPath !== '' ? $resolvedPath : $path;
        return rtrim($finalPath, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * @param $namespace
     * @return string
     */
    public static function extractClassFromNamespace($namespace): string
    {
        $parts = preg_split('/(\\\|\\/)/', $namespace);
        return array_pop($parts);
    }

    /**
     * @param $namespace
     * @throws GeneratorException
     */
    public static function checkCustomNamespaceApi($namespace): void
    {
        if (!empty($namespace)) {
            if (class_exists($namespace)) {
                $reflector = new ReflectionClass($namespace);
                if (!$reflector->isSubclassOf(Api::class)) {
                    throw new GeneratorException(t('The defined class must extend PSFS\\\base\\\types\\\Api'), 501);
                } elseif (!$reflector->isAbstract()) {
                    throw new GeneratorException(t('The defined class must be abstract'), 501);
                }
            } else {
                throw new GeneratorException(t('The defined class for extending API does not exist'), 501);
            }
        }
    }

    /**
     * @param string $path
     * @param OutputInterface|null $output
     * @param boolean $quiet
     * @throws GeneratorException
     */
    public static function createRoot($path = WEB_DIR, $output = null, $quiet = false): void
    {
        $output = $output ?? new ConsoleOutput();
        self::createDocumentRootStructure($path);
        $files = self::getRootFilesToGenerate();
        $verifiable = ['humans', 'robots', 'docker'];
        if (!$quiet) {
            $output->writeln('Start creating html files');
        }
        foreach ($files as $template => $filename) {
            $target = $path . DIRECTORY_SEPARATOR . $filename;
            if (in_array($template, $verifiable, true) && file_exists($target)) {
                self::writeGeneratorOutput($output, $quiet, $filename . ' already exists');
                continue;
            }
            $text = Template::getInstance()->dump(
                "generator/html/" . $template . '.html.twig',
                ['PSFS_AS_VENDOR' => PSFS_AS_VENDOR]
            );
            if (!FileHelper::writeFileAtomic($target, $text)) {
                self::writeGeneratorOutput($output, $quiet, 'Can\t create the file ' . $filename);
                continue;
            }
            self::writeGeneratorOutput($output, $quiet, $filename . ' created successfully');
        }
        self::ensureBaseLocaleExists();
    }

    private static function createDocumentRootStructure(string $path): void
    {
        self::createDir($path);
        foreach (["js", "css", "img", "media", "font"] as $htmlPath) {
            self::createDir($path . DIRECTORY_SEPARATOR . $htmlPath);
        }
    }

    private static function getRootFilesToGenerate(): array
    {
        return [
            'index' => 'index.php',
            'browserconfig' => 'browserconfig.xml',
            'crossdomain' => 'crossdomain.xml',
            'humans' => 'humans.txt',
            'robots' => 'robots.txt',
            'docker' => '..' . DIRECTORY_SEPARATOR . 'docker-compose.yml',
        ];
    }

    private static function ensureBaseLocaleExists(): void
    {
        if (!file_exists(BASE_DIR . DIRECTORY_SEPARATOR . 'locale')) {
            self::createDir(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
            self::copyr(
                SOURCE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'locale',
                BASE_DIR . DIRECTORY_SEPARATOR . 'locale'
            );
        }
    }

    private static function writeGeneratorOutput(OutputInterface $output, bool $quiet, string $message): void
    {
        if (!$quiet) {
            $output->writeln($message);
        }
    }

    /**
     * @param string $dest
     * @param boolean $force
     * @param string $filenamePath
     * @param boolean $debug
     * @throws GeneratorException
     */
    public static function copyResources($dest, $force, $filenamePath, $debug): void
    {
        if (file_exists($filenamePath)) {
            $destfolder = basename($filenamePath);
            if (!file_exists(WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder) || $debug || $force) {
                if (is_dir($filenamePath)) {
                    self::copyr($filenamePath, WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder);
                } else {
                    if (!FileHelper::copyFileAtomic(
                        $filenamePath,
                        WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder
                    )) {
                        throw new ConfigException(
                            "Can't copy " . $filenamePath . " to " . WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder
                        );
                    }
                }
            }
        }
    }

    /**
     * @param string $src
     * @param string $dst
     * @throws GeneratorException
     */
    public static function copyr($src, $dst): void
    {
        FilesystemTreeHelper::copyRecursive((string)$src, (string)$dst);
    }
}
