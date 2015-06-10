<?php

namespace PSFS\base\extension;

use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\lib\CssMinifier;
use PSFS\base\lib\JsMinifier;
use PSFS\base\Logger;
use PSFS\base\Template;

/**
 * Class AssetsParser
 * @package PSFS\base\extension
 */
class AssetsParser {

    protected $files = array();
    protected $hash = array();
    protected $compiled_files;
    protected $type;
    protected $path;
    protected $domains = array();
    private $log;

    /**
     * Constructor por defecto
     *
     * @param string $type
     */
    public function __construct($type = 'js')
    {
        $this->type = $type;
        $this->path = WEB_DIR.DIRECTORY_SEPARATOR;
        $this->domains = Template::getDomains(true);
        $this->log = Logger::getInstance();
    }

    /**
     * Método que añade un nuevo fichero al proceso de generación de los assets
     * @param $filename
     * @return $this
     * @internal param string $type
     *
     */
    public function addFile($filename)
    {
        if (file_exists($this->path.$filename) && preg_match('/\.'.$this->type.'$/i', $filename)) $this->files[] = $filename;
        elseif (!empty($this->domains)) foreach ($this->domains as $domain => $paths)
        {
            $domain_filename = str_replace($domain, $paths["public"], $filename);
            if (file_exists($domain_filename) && preg_match('/\.'.$this->type.'$/i', $domain_filename))
            {
                $this->files[] = $domain_filename;
            }
        }
        return $this;
    }

    /**
     * Método que establece el hash con el que compilar los assets
     * @param $hash
     *
     * @return $this
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     * Método que procesa los ficheros solicitados en función del modo de ejecución
     * @return $this
     * @internal param string $type
     *
     */
    public function compile()
    {
        /* @var $config \PSFS\base\config\Config */
        $config = Config::getInstance();
        $debug = $config->getDebugMode();
        //Unificamos ficheros para que no se retarde mucho el proceso
        $this->files = array_unique($this->files);
        switch ($this->type)
        {
            default:
            case "js": $this->_compileJs($debug); break;
            case "css": $this->_compileCss($debug); break;
        }

        return $this;
    }

    /**
     * Método que compila los ficheros css y los procesa en función del modo de ejecución
     * @param bool $debug
     *
     * @return $this
     */
    protected function _compileCss($debug = false)
    {
        $base = $this->path."css".DIRECTORY_SEPARATOR;
        Config::createDir($base);
        $data = '';
        if (!empty($this->files)) foreach ($this->files as $file)
        {
            $data = $this->processCssLine($debug, $file, $base, $data);
        }
        if (!$debug && !file_exists($base.$this->hash.".css"))
        {
            $minifier = new CssMinifier();
            $data = $minifier->run($data);
            file_put_contents($base.$this->hash.".css", $data);
        }
        return $this;
    }

    /**
     * Método que compila los ficheros javascript en función del modo de ejecución
     * @param bool $debug
     *
     * @return $this
     */
    protected function _compileJs($debug = false)
    {
        $base = $this->path."js".DIRECTORY_SEPARATOR;
        Config::createDir($base);
        $data = '';
        if (!empty($this->files)) foreach ($this->files as $file)
        {
            $path_parts = explode("/", $file);
            if (file_exists($file))
            {
                if ($debug)
                {
                    $data = $this->putDebugJs($path_parts, $base, $file);
                }else {
                    $data = $this->putProductionJs($base, $file, $data);
                }
            }
        }
        if (!$debug && !file_exists($base.$this->hash.".js"))
        {
            file_put_contents($base.$this->hash.".js", $data);
        }
        return $this;
    }

    /**
     * Método que imprime el resultado de la generación de los assets
     */
    public function printHtml()
    {
        /* @var $config \PSFS\base\config\Config */
        $config = Config::getInstance();
        $debug = $config->getDebugMode();
        switch ($this->type)
        {
            default:
            case "js": $this->_printJs($debug); break;
            case "css": $this->_printCss($debug); break;
        }
    }

    /**
     * Método que devuelve el html con la ruta compilada del recurso javascript
     * @param boolean $debug
     */
    protected function _printJs($debug)
    {
        if ($debug)
        {
            if (!empty($this->compiled_files)) foreach ($this->compiled_files as $file)
            {
                echo "\t\t<script type='text/javascript' src='{$file}'></script>\n";
            }
        }else {
            echo "\t\t<script type='text/javascript' src='/js/".$this->hash.".js'></script>\n";
        }
    }

    /**
     * Método que devuelve el html con la ruta compilada del recurso css
     * @param boolean $debug
     */
    protected function _printCss($debug)
    {
        if ($debug)
        {
            if (!empty($this->compiled_files)) foreach ($this->compiled_files as $file)
            {
                echo "\t\t<link href='{$file}' rel='stylesheet' media='screen, print'>";
            }
        }else {
            echo "\t\t<link href='/css/".$this->hash.".css' rel='stylesheet' media='screen, print'>";
        }
    }

    /**
     * @param $source
     * @param $file
     */
    protected function extractCssResources($source, $file)
    {
        $source_file = preg_replace("/'/", "", $source[1]);
        if (preg_match('/\#/', $source_file)) {
            $source_file = explode("#", $source_file);
            $source_file = $source_file[0];
        }
        if (preg_match('/\?/', $source_file)) {
            $source_file = explode("?", $source_file);
            $source_file = $source_file[0];
        }
        $orig = realpath(dirname($file) . DIRECTORY_SEPARATOR . $source_file);
        $orig_part = preg_split('/\/public\//i', $orig);
        try {
            if (count($orig_part) > 1) {
                $dest = $this->path . $orig_part[1];
                Config::createDir($dest);
                if (!file_exists($dest) || filemtime($orig) > filemtime($dest)) {
                    if (@copy($orig, $dest) === FALSE) {
                        throw new \RuntimeException('Can\' copy ' . $dest . '');
                    }
                    $this->log->infoLog("$orig copiado a $dest");
                }
            }
        } catch (\Exception $e) {
            $this->log->errorLog($e->getMessage());
        }
    }

    /**
     * @param $debug
     * @param $file
     * @param $base
     * @param $data
     *
     * @return string
     */
    protected function processCssLine($debug, $file, $base, $data)
    {
        if (file_exists($file)) {

            $path_parts = explode("/", $file);
            $file_path = $this->hash . "_" . $path_parts[count($path_parts) - 1];
            if (!file_exists($base . $file_path) || filemtime($base . $file_path) < filemtime($file)) {
                //Si tenemos modificaciones tenemos que compilar de nuevo todos los ficheros modificados
                if(@unlink($base . $this->hash . ".css") === false) {
                    throw new ConfigException("Can't unlink file " . $base . $this->hash . ".css");
                }
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
            if ($debug) {
                $data = file_get_contents($file);
                file_put_contents($base . $file_path, $data);
            } else {
                $data .= file_get_contents($file);
            }
            $this->compiled_files[] = "/css/" . $file_path;

            return $data;
        }

        return $data;
    }

    /**
     * @param $path_parts
     * @param $base
     * @param $file
     *
     * @return string
     */
    protected function putDebugJs($path_parts, $base, $file)
    {
        $file_path = $this->hash . "_" . $path_parts[count($path_parts) - 1];
        $this->compiled_files[] = "/js/" . $file_path;
        $data = "";
        if (!file_exists($base . $file_path) || filemtime($base . $file_path) < filemtime($file)) {
            $data = file_get_contents($file);
            file_put_contents($base . $file_path, $data);
        }

        return $data;
    }

    /**
     * @param $base
     * @param $file
     * @param $data
     *
     * @return string
     * @throws \Exception
     */
    protected function putProductionJs($base, $file, $data)
    {
        if (!file_exists($base . $this->hash . ".js")) {
            $js = file_get_contents($file);
            $data .= ";" . $minifiedCode = JsMinifier::minify($js);
        }

        return $data;
    }
}