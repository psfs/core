<?php
namespace PSFS\base\extension\traits;

use MatthiasMullie\Minify\JS;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\AssetsHelper;
use PSFS\base\types\helpers\GeneratorHelper;

/**
 * Trait JsTrait
 * @package PSFS\base\extension\traits
 */
trait JsTrait {

    use SRITrait;

    /**
     * @param array $compiledFiles
     * @param string $baseUrl
     * @param string $hash
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function printJs(array $compiledFiles, $baseUrl, $hash)
    {
        if (Config::getParam('debug') && 0 < count($compiledFiles)) {
            foreach ($compiledFiles as $file) {
                echo "\t\t<script type='text/javascript' src='{$file}'></script>\n";
            }
        } else {
            $sri = $this->getSriHash($hash, 'js');
            echo "\t\t<script type='text/javascript' src='" . $baseUrl . "/js/" . $hash . ".js'" .
                " crossorigin='anonymous' integrity='sha384-" . $sri . "'></script>\n";
        }
    }

    /**
     * @param $pathParts
     * @param string $base
     * @param string $file
     * @param string $hash
     * @param array $compiledFiles
     * @return false|string
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function putDebugJs($pathParts, $base, $file, $hash, array &$compiledFiles)
    {
        $filePath = $hash . "_" . $pathParts[count($pathParts) - 1];
        $compiledFiles[] = "/js/" . $filePath;
        $data = "";
        if (!file_exists($base . $filePath) || filemtime($base . $filePath) < filemtime($file)) {
            $data = file_get_contents($file);
            AssetsHelper::storeContents($base . $filePath, $data);
        }
        return $data;
    }

    /**
     * Método que compila los ficheros javascript en función del modo de ejecución
     * @param array $files
     * @param string $basePath
     * @param string $hash
     * @param array $compiledFiles
     * @return $this
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function compileJs(array $files, $basePath, $hash, array &$compiledFiles)
    {
        $base = $basePath . "js" . DIRECTORY_SEPARATOR;
        $debug = Config::getParam('debug');
        if ($debug || !file_exists($base . $hash . ".js")) {
            $data = '';
            if (0 < count($files)) {
                $minifier = new JS();
                foreach ($files as $file) {
                    $pathParts = explode("/", $file);
                    if (file_exists($file)) {
                        if ($debug) {
                            $data = $this->putDebugJs($pathParts, $base, $file, $hash, $compiledFiles);
                        } elseif (!file_exists($base . $hash . ".js")) {
                            $minifier->add($file);
                            //$data = $this->putProductionJs($base, $file, $data);
                        }
                    }
                }
                if($debug) {
                    AssetsHelper::storeContents($base . $hash . ".js", $data);
                } else {
                    $this->dumpJs($hash, $base, $minifier);
                }
                unset($minifier);
            }
        }
        return $this;
    }

    /**
     * @param $hash
     * @param string $base
     * @param JS $minifier
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function dumpJs($hash, string $base, JS $minifier)
    {
        ini_set('max_execution_time', -1);
        ini_set('memory_limit', -1);
        GeneratorHelper::createDir($base);
        if (Config::getParam('assets.obfuscate', false)) {
            $minifier->gzip($base . $hash . ".js");
        } else {
            $minifier->minify($base . $hash . ".js");
        }
    }
}
