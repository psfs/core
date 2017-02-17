<?php

namespace PSFS\base\extension;

use JShrink\Minifier;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\Template;
use PSFS\base\types\helpers\GeneratorHelper;

/**
 * Class AssetsParser
 * @package PSFS\base\extension
 */
class AssetsParser
{

    protected $files = [];
    protected $hash = [];
    protected $compiled_files;
    protected $type;
    protected $path;
    protected $domains = [];
    private $debug = false;

    /**
     * Constructor por defecto
     *
     * @param string $type
     */
    public function __construct($type = 'js')
    {
        $this->type = $type;
        $this->path = WEB_DIR . DIRECTORY_SEPARATOR;
        $this->domains = Template::getDomains(true);
        $this->debug = Config::getInstance()->getDebugMode();
    }

    /**
     * Método que calcula el path completo a copiar un recurso
     * @param string $filename_path
     * @param string[] $source
     * @return string
     */
    protected static function calculateResourcePathname($filename_path, $source)
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
        $orig = realpath(dirname($filename_path) . DIRECTORY_SEPARATOR . $source_file);
        return $orig;
    }

    /**
     * Método que añade un nuevo fichero al proceso de generación de los assets
     * @param $filename
     * @return AssetsParser
     * @internal param string $type
     *
     */
    public function addFile($filename)
    {
        if (file_exists($this->path . $filename) && preg_match('/\.' . $this->type . '$/i', $filename)) {
            $this->files[] = $filename;
        } elseif (!empty($this->domains)) {
            foreach ($this->domains as $domain => $paths) {
                $domain_filename = str_replace($domain, $paths["public"], $filename);
                if (file_exists($domain_filename) && preg_match('/\.' . $this->type . '$/i', $domain_filename)) {
                    $this->files[] = $domain_filename;
                }
            }
        }
        return $this;
    }

    /**
     * Método que establece el hash con el que compilar los assets
     * @param string $hash
     *
     * @return AssetsParser
     */
    public function setHash($hash)
    {
        $cache = Config::getParam('cache.var', '');
        $this->hash = $hash . (strlen($cache) ? '.' : '') . $cache;
        return $this;
    }

    /**
     * Método que procesa los ficheros solicitados en función del modo de ejecución
     * @return AssetsParser
     * @internal param string $type
     * @throws ConfigException
     */
    public function compile()
    {
        //Unificamos ficheros para que no se retarde mucho el proceso
        $this->files = array_unique($this->files);
        switch ($this->type) {
            default:
            case "js":
                $this->compileJs();
                break;
            case "css":
                $this->compileCss();
                break;
        }

        return $this;
    }

    /**
     * Método que compila los ficheros css y los procesa en función del modo de ejecución
     * @return AssetsParser
     * @throws ConfigException
     */
    protected function compileCss()
    {
        $base = $this->path . "css" . DIRECTORY_SEPARATOR;
        if ($this->debug || !file_exists($base . $this->hash . ".css")) {
            $data = '';
            if (0 < count($this->files)) {
                foreach ($this->files as $file) {
                    $data = $this->processCssLine($file, $base, $data);
                }
            }
            $this->storeContents($base . $this->hash . ".css", \CssMin::minify($data));
            unset($cssMinifier);
        }
        return $this;
    }

    /**
     * Método que compila los ficheros javascript en función del modo de ejecución
     * @return $this
     * @throws ConfigException
     */
    protected function compileJs()
    {
        $base = $this->path . "js" . DIRECTORY_SEPARATOR;
        if ($this->debug || !file_exists($base . $this->hash . ".js")) {
            $data = '';
            if (0 < count($this->files)) {
                foreach ($this->files as $file) {
                    $path_parts = explode("/", $file);
                    if (file_exists($file)) {
                        if ($this->debug) {
                            $data = $this->putDebugJs($path_parts, $base, $file);
                        } elseif (!file_exists($base . $this->hash . ".js")) {
                            $data = $this->putProductionJs($base, $file, $data);
                        }
                    }
                }
            }
            try {
                $minifiedJs = Minifier::minify($data);
            } catch (\Exception $e) {
                Logger::log($e->getMessage(), LOG_ERR);
                $minifiedJs = false;
            }
            $this->storeContents($base . $this->hash . ".js", (false !== $minifiedJs) ? $minifiedJs : $data);
        }
        return $this;
    }

    /**
     * Método para guardar cualquier contenido y controlar que existe el directorio y se guarda correctamente
     * @param string $path
     * @param string $content
     * @throws ConfigException
     */
    private function storeContents($path, $content = "")
    {
        GeneratorHelper::createDir(dirname($path));
        if ("" !== $content && false === file_put_contents($path, $content)) {
            throw new ConfigException(_('No se tienen permisos para escribir en ' . $path));
        }
    }

    /**
     * Método que imprime el resultado de la generación de los assets
     */
    public function printHtml()
    {
        switch ($this->type) {
            default:
            case "js":
                $this->printJs();
                break;
            case "css":
                $this->printCss();
                break;
        }
    }

    /**
     * Método que devuelve el html con la ruta compilada del recurso javascript
     */
    protected function printJs()
    {
        if ($this->debug && 0 < count($this->compiled_files)) {
            foreach ($this->compiled_files as $file) {
                echo "\t\t<script type='text/javascript' src='{$file}'></script>\n";
            }
        } else {
            echo "\t\t<script type='text/javascript' src='/js/" . $this->hash . ".js'></script>\n";
        }
    }

    /**
     * Método que devuelve el html con la ruta compilada del recurso css
     */
    protected function printCss()
    {
        if ($this->debug && 0 < count($this->compiled_files)) {
            foreach ($this->compiled_files as $file) {
                echo "\t\t<link href='{$file}' rel='stylesheet' media='screen, print'>";
            }
        } else {
            echo "\t\t<link href='/css/" . $this->hash . ".css' rel='stylesheet'>";
        }
    }

    /**
     * @param string $source
     * @param string $file
     */
    protected function extractCssResources($source, $file)
    {
        $source_file = $this->extractSourceFilename($source);
        $orig = realpath(dirname($file) . DIRECTORY_SEPARATOR . $source_file);
        $orig_part = preg_split('/(\/|\\\)public(\/|\\\)/i', $orig);
        try {
            if (count($source) > 1 && array_key_exists(1, $orig_part)) {
                $dest = $this->path . $orig_part[1];
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
    }

    /**
     * Método que procesa cada línea de la hoja de estilos para copiar los recursos asociados
     * @param string $file
     * @param string $base
     * @param string $data
     * @return string
     * @throws ConfigException
     */
    protected function processCssLine($file, $base, $data)
    {
        if (file_exists($file)) {
            $path_parts = explode("/", $file);
            $file_path = $this->hash . "_" . $path_parts[count($path_parts) - 1];
            if (!file_exists($base . $file_path) || filemtime($base . $file_path) < filemtime($file) || $this->debug) {
                //Si tenemos modificaciones tenemos que compilar de nuevo todos los ficheros modificados
                if (file_exists($base . $this->hash . ".css") && @unlink($base . $this->hash . ".css") === false) {
                    throw new ConfigException("Can't unlink file " . $base . $this->hash . ".css");
                }
                $this->loopCssLines($file);
            }
            if ($this->debug) {
                $data = file_get_contents($file);
                $this->storeContents($base . $file_path, $data);
            } else {
                $data .= file_get_contents($file);
            }
            $this->compiled_files[] = "/css/" . $file_path;
        }

        return $data;
    }

    /**
     * @param $path_parts
     * @param string $base
     * @param $file
     * @return string
     * @throws ConfigException
     */
    protected function putDebugJs($path_parts, $base, $file)
    {
        $file_path = $this->hash . "_" . $path_parts[count($path_parts) - 1];
        $this->compiled_files[] = "/js/" . $file_path;
        $data = "";
        if (!file_exists($base . $file_path) || filemtime($base . $file_path) < filemtime($file)) {
            $data = file_get_contents($file);
            $this->storeContents($base . $file_path, $data);
        }
        return $data;
    }

    /**
     * @param string $base
     * @param $file
     * @param string $data
     *
     * @return string
     * @throws ConfigException
     */
    protected function putProductionJs($base, $file, $data)
    {
        $js = file_get_contents($file);
        try {
            $data .= ";\n" . $js;
        } catch (\Exception $e) {
            throw new ConfigException($e->getMessage());
        }
        return $data;
    }

    /**
     * Servicio que busca el path para un dominio dado
     * @param $string
     * @param string $file_path
     *
     * @return string
     */
    public static function findDomainPath($string, $file_path)
    {
        $domains = Template::getDomains(TRUE);
        $filename_path = null;
        if (!file_exists($file_path) && 0 < count($domains)) {
            foreach ($domains as $domain => $paths) {
                $domain_filename = str_replace($domain, $paths["public"], $string);
                if (file_exists($domain_filename)) {
                    $filename_path = $domain_filename;
                    continue;
                }
            }
        }

        return $filename_path;
    }

    /**
     * Método que calcula el path de un recurso web
     * @param string $string
     * @param string $name
     * @param boolean $return
     * @param string $filename_path
     *
     * @return string[]
     */
    public static function calculateAssetPath($string, $name, $return, $filename_path)
    {
        $ppath = explode("/", $string);
        $original_filename = $ppath[count($ppath) - 1];
        $base = WEB_DIR . DIRECTORY_SEPARATOR;
        $file = "";
        $html_base = "";
        $debug = Config::getInstance()->getDebugMode();
        $cache = Config::getInstance()->get('cache.var');
        $cache = $cache ? '.' . $cache : '';
        if (preg_match('/\.css$/i', $string)) {
            $file = "/" . substr(md5($string), 0, 8) . "$cache.css";
            $html_base = "css";
            if ($debug) {
                $file = str_replace(".css", "_" . $original_filename, $file);
            }
        } elseif (preg_match('/\.js$/i', $string)) {
            $file = "/" . substr(md5($string), 0, 8) . "$cache.js";
            $html_base = "js";
            if ($debug) {
                $file = str_replace(".js", "_" . $original_filename, $file);
            }
        } elseif (preg_match("/image/i", mime_content_type($filename_path))) {
            $ext = explode(".", $string);
            $file = "/" . substr(md5($string), 0, 8) . "." . $ext[count($ext) - 1];
            $html_base = "img";
            if ($debug) {
                $file = str_replace("." . $ext[count($ext) - 1], "_" . $original_filename, $file);
            }
        } elseif (preg_match("/(doc|pdf)/i", mime_content_type($filename_path))) {
            $ext = explode(".", $string);
            $file = "/" . substr(md5($string), 0, 8) . "." . $ext[count($ext) - 1];
            $html_base = "docs";
            if ($debug) {
                $file = str_replace("." . $ext[count($ext) - 1], "_" . $original_filename, $file);
            }
        } elseif (preg_match("/(video|audio|ogg)/i", mime_content_type($filename_path))) {
            $ext = explode(".", $string);
            $file = "/" . substr(md5($string), 0, 8) . "." . $ext[count($ext) - 1];
            $html_base = "media";
            if ($debug) {
                $file = str_replace("." . $ext[count($ext) - 1], "_" . $original_filename, $file);
            }
        } elseif (!$return && !is_null($name)) {
            $html_base = '';
            $file = $name;
        }
        $file_path = $html_base . $file;

        return array($base, $html_base, $file_path);
    }

    /**
     * Método que extrae el recurso css de una línea de estilos css
     * @param $handle
     * @param string $filename_path
     * @throws ConfigException
     */
    public static function extractCssLineResource($handle, $filename_path)
    {
        $line = fgets($handle);
        $urls = array();
        if (preg_match_all('#url\((.*?)\)#', $line, $urls, PREG_SET_ORDER)) {
            foreach ($urls as $source) {
                $orig = self::calculateResourcePathname($filename_path, $source);
                $orig_part = explode("Public", $orig);
                $dest = WEB_DIR . $orig_part[1];
                GeneratorHelper::createDir(dirname($dest));
                if (@copy($orig, $dest) === false) {
                    throw new ConfigException("Can't copy " . $orig . " to " . $dest);
                }
            }
        }
    }

    /**
     * Método que extrae el nombre del fichero de un recurso
     * @param string $source
     * @return string
     */
    protected function extractSourceFilename($source)
    {
        $source_file = preg_replace("/'/", "", $source[1]);
        if (preg_match('/\#/', $source_file)) {
            $source_file = explode("#", $source_file);
            $source_file = $source_file[0];
        }
        if (preg_match('/\?/', $source_file)) {
            $source_file = explode("?", $source_file);
            $source_file = $source_file[0];
            return $source_file;
        }
        return $source_file;
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
}
