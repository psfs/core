<?php
namespace PSFS\base\extension\traits;

use MatthiasMullie\Minify\JS;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\types\helpers\GeneratorHelper;

/**
 * Trait JsTrait
 * @package PSFS\base\extension\traits
 */
trait JsTrait {


    /**
     * @param array $compiledFiles
     * @param string $baseUrl
     * @param string $hash
     * @param bool $debug
     */
    protected function printJs(array $compiledFiles, $baseUrl, $hash, $debug = false)
    {
        if ($debug && 0 < count($compiledFiles)) {
            foreach ($compiledFiles as $file) {
                echo "\t\t<script type='text/javascript' src='{$file}'></script>\n";
            }
        } else {
            echo "\t\t<script type='text/javascript' src='" . $baseUrl . "/js/" . $hash . ".js'></script>\n";
        }
    }

    /**
     * @param $pathParts
     * @param string $base
     * @param $file
     * @return false|string
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function putDebugJs($pathParts, $base, $file)
    {
        $filePath = $this->hash . "_" . $pathParts[count($pathParts) - 1];
        $this->compiled_files[] = "/js/" . $filePath;
        $data = "";
        if (!file_exists($base . $filePath) || filemtime($base . $filePath) < filemtime($file)) {
            $data = file_get_contents($file);
            $this->storeContents($base . $filePath, $data);
        }
        return $data;
    }

    /**
     * Método para guardar cualquier contenido y controlar que existe el directorio y se guarda correctamente
     * @param string $path
     * @param string $content
     * @throws \PSFS\base\exception\GeneratorException
     */
    private function storeContents($path, $content = "")
    {
        GeneratorHelper::createDir(dirname($path));
        if ("" !== $content && false === file_put_contents($path, $content)) {
            throw new ConfigException(t('No se tienen permisos para escribir en ' . $path));
        }
    }

    /**
     * Método que compila los ficheros javascript en función del modo de ejecución
     * @return $this
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function compileJs(array $files, $basePath, $hash, $debug = false)
    {
        $base = $basePath . "js" . DIRECTORY_SEPARATOR;
        if ($debug || !file_exists($base . $hash . ".js")) {
            $data = '';
            if (0 < count($files)) {
                $minifier = new JS();
                foreach ($files as $file) {
                    $pathParts = explode("/", $file);
                    if (file_exists($file)) {
                        if ($debug) {
                            $data = $this->putDebugJs($pathParts, $base, $file);
                        } elseif (!file_exists($base . $hash . ".js")) {
                            $minifier->add($file);
                            //$data = $this->putProductionJs($base, $file, $data);
                        }
                    }
                }
                if($debug) {
                    $this->storeContents($base . $hash . ".js", $data);
                } else {
                    ini_set('max_execution_time', -1);
                    ini_set('memory_limit', -1);
                    GeneratorHelper::createDir($base);
                    if(Config::getParam('assets.obfuscate', false)) {
                        $minifier->gzip($base . $hash . ".js");
                    } else {
                        $minifier->minify($base . $hash . ".js");
                    }
                }
                unset($minifier);
            }
        }
        return $this;
    }
}
