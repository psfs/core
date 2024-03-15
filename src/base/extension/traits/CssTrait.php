<?php

namespace PSFS\base\extension\traits;

use MatthiasMullie\Minify\CSS;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\types\helpers\AssetsHelper;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\Inspector;

/**
 * Trait CssTrait
 * @package PSFS\base\extension\traits
 */
trait CssTrait
{

    /**
     * @var string
     */
    protected $path;

    /**
     * @param string $basePath
     * @param string $hash
     * @param bool $debug
     * @return $this
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function compileCss($basePath, $hash)
    {
        $debug = Config::getParam('debug');
        $base = $basePath . "css" . DIRECTORY_SEPARATOR;
        if ($debug || !file_exists($base . $hash . ".css")) {
            $data = '';
            if (0 < count($this->files)) {
                $minifier = new CSS();
                foreach ($this->files as $file) {
                    $data = $this->processCssLine($file, $base, $data, $hash);
                }
            }
            if ($debug) {
                AssetsHelper::storeContents($base . $hash . ".css", $data);
            } else {
                $minifier = new CSS();
                $minifier->add($data);
                ini_set('max_execution_time', -1);
                ini_set('memory_limit', -1);
                GeneratorHelper::createDir($base);
                $minifier->minify($base . $hash . ".css");
            }
            unset($minifier);
        }
        return $this;
    }

    /**
     * Método que procesa cada línea de la hoja de estilos para copiar los recursos asociados
     * @param string $file
     * @param string $base
     * @param string $data
     * @return false|string
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function processCssLine($file, $base, $data, $hash)
    {
        if (file_exists($file)) {
            $debug = Config::getParam('debug');
            $pathParts = explode("/", $file);
            $filePath = $this->hash . "_" . $pathParts[count($pathParts) - 1];
            if (!file_exists($base . $filePath) || filemtime($base . $filePath) < filemtime($file) || $debug) {
                //Si tenemos modificaciones tenemos que compilar de nuevo todos los ficheros modificados
                if (file_exists($base . $hash . ".css") && @unlink($base . $hash . ".css") === false) {
                    throw new ConfigException("Can't unlink file " . $base . $hash . ".css");
                }
                $this->loopCssLines($file);
            }
            if ($debug) {
                $data = file_get_contents($file);
                AssetsHelper::storeContents($base . $filePath, $data);
            } else {
                $data .= file_get_contents($file);
            }
            $this->compiledFiles[] = "/css/" . $filePath;
        }

        return $data;
    }

    /**
     * @param string $file
     */
    protected function loopCssLines($file)
    {
        $handle = @fopen($file, 'r');
        if ($handle) {
            while (!feof($handle)) {
                $line = fgets($handle);
                $urls = array();
                if (preg_match_all('#url\((.*?)\)#', $line, $urls, PREG_SET_ORDER)) {
                    foreach ($urls as $source) {
                        $this->extractCssResources($source, $file);
                    }
                }
            }
            fclose($handle);
        }
    }

    /**
     * @param string|array $source
     * @param string $file
     */
    protected function extractCssResources($source, $file)
    {
        Inspector::stats('[CssTrait] Start collecting resources from ' . $file, Inspector::SCOPE_DEBUG);
        $sourceFile = AssetsHelper::extractSourceFilename($source);
        $orig = realpath(dirname($file) . DIRECTORY_SEPARATOR . $sourceFile);
        $origPart = preg_split('/(\/|\\\)public(\/|\\\)/i', $orig);
        try {
            if (count($source) > 1 && array_key_exists(1, $origPart)) {
                $dest = $this->path . $origPart[1];
                GeneratorHelper::createDir(dirname($dest));
                if (!file_exists($dest) || filemtime($orig) > filemtime($dest)) {
                    if (@copy($orig, $dest) === FALSE) {
                        throw new \RuntimeException('Can\' copy ' . $dest . '');
                    }
                    Logger::log("$orig copiado a $dest", LOG_INFO);
                }
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
        }
        Inspector::stats('[CssTrait] End collecting resources from ' . $file, Inspector::SCOPE_DEBUG);
    }

    /**
     * @param array $compiledFiles
     * @param string $baseUrl
     * @param string $hash
     */
    protected function printCss(array $compiledFiles, $baseUrl, $hash)
    {
        if (Config::getParam('debug') && 0 < count($compiledFiles)) {
            foreach ($compiledFiles as $file) {
                echo "\t\t<link href='{$file}' rel='stylesheet' media='screen, print'>";
            }
        } else {
            echo "\t\t<link href='" . $baseUrl . "/css/" . $hash . ".css' rel='stylesheet' " .
                //"crossorigin='anonymous' integrity='sha384-" . $sri . "'>";
                "media='screen, print'>";
        }
    }
}
