<?php

namespace PSFS\base\extension;

use \PSFS\base\config\Config;
use \PSFS\base\lib\JsMinifier;
use \PSFS\base\lib\CssMinifier;
use PSFS\base\Template;

/**
 * Class AssetsParser
 * @package PSFS\base\extension
 */
class AssetsParser{

    protected $files = array();
    protected $hash = array();
    protected $compiled_files;
    protected $type;
    protected $path;
    protected $domains = array();

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
        if(file_exists($this->path . $filename) && preg_match('/\.' . $this->type . '$/i', $filename)) $this->files[] = $filename;
        elseif(!empty($this->domains)) foreach($this->domains as $domain => $paths)
        {
            $domain_filename = str_replace($domain, $paths["public"], $filename);
            if(file_exists($domain_filename) && preg_match('/\.' . $this->type . '$/i', $domain_filename))
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
        $debug = $config->get("debug");
        //Unificamos ficheros para que no se retarde mucho el proceso
        $this->files = array_unique($this->files);
        switch($this->type)
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
        $base = $this->path . "css" . DIRECTORY_SEPARATOR;
        if(!file_exists($base)) @mkdir($base);
        $data = '';
        if(!empty($this->files)) foreach($this->files as $file)
        {
            if(file_exists($file))
            {

                $path_parts = explode("/", $file);
                $file_path = $this->hash . "_" . $path_parts[count($path_parts) - 1];
                if(!file_exists($base . $file_path) || filemtime($base . $file_path) < filemtime($file))
                {
                    //Si tenemos modificaciones tenemos que compilar de nuevo todos los ficheros modificados
                    @unlink($base . $this->hash . ".css");
                    $handle = @fopen($file, 'r');
                    if($handle)
                    {
                        while (!feof($handle)) {
                            $line = fgets($handle);
                            $urls = array();
                            if(preg_match_all('#url\((.*?)\)#', $line, $urls, PREG_SET_ORDER))
                            {
                                foreach($urls as $source)
                                {
                                    $source_file = preg_replace("/'/", "", $source[1]);
                                    if(preg_match('/\#/', $source_file))
                                    {
                                        $source_file = explode("#", $source_file);
                                        $source_file = $source_file[0];
                                    }
                                    if(preg_match('/\?/', $source_file))
                                    {
                                        $source_file = explode("?", $source_file);
                                        $source_file = $source_file[0];
                                    }
                                    $orig = realpath(dirname($file) . DIRECTORY_SEPARATOR . $source_file);
                                    $orig_part = preg_split('/\/public\//i', $orig);
                                    try
                                    {
                                        $dest = $this->path . $orig_part[1];
                                        if(!file_exists(dirname($dest))) @mkdir(dirname($dest), 0755, true);
                                        @copy($orig, $dest);
                                    }catch(\Exception $e)
                                    {
                                    }
                                }
                            }
                        }
                        fclose($handle);
                    }
                }
                if($debug)
                {
                    $data = file_get_contents($file);
                    file_put_contents($base . $file_path, $data);
                }else{
                    $data .= file_get_contents($file);
                }
                $this->compiled_files[] = "/css/" . $file_path;
            }
        }
        if(!$debug && !file_exists($base . $this->hash . ".css"))
        {
            $minifier = new CssMinifier();
            $data = $minifier->run($data);
            file_put_contents($base . $this->hash . ".css", $data);
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
        $base = $this->path . "js" . DIRECTORY_SEPARATOR;
        if(!file_exists($base)) @mkdir($base);
        $data = '';
        if(!empty($this->files)) foreach($this->files as $file)
        {
            $path_parts = explode("/", $file);
            if(file_exists($file))
            {
                if($debug)
                {
                    $file_path = $this->hash . "_" . $path_parts[count($path_parts) - 1];
                    $this->compiled_files[] = "/js/" . $file_path;
                    if(!file_exists($base . $file_path) || filemtime($base . $file_path) < filemtime($file))
                    {
                        $data = file_get_contents($file);
                        file_put_contents($base . $file_path, $data);
                    }
                }else{
                    if(!file_exists($base . $this->hash . ".js"))
                    {
                        $js = file_get_contents($file);
                        $data .= ";" . $minifiedCode = JsMinifier::minify($js);
                    }
                }
            }
        }
        if(!$debug && !file_exists($base . $this->hash . ".js"))
        {
            file_put_contents($base . $this->hash . ".js", $data);
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
        $debug = $config->get("debug");
        switch($this->type)
        {
            default:
            case "js": $this->_printJs($debug); break;
            case "css": $this->_printCss($debug); break;
        }
    }

    /**
     * Método que devuelve el html con la ruta compilada del recurso javascript
     * @param $debug
     */
    protected function _printJs($debug)
    {
        if($debug)
        {
            if(!empty($this->compiled_files)) foreach($this->compiled_files as $file)
            {
                echo "\t\t<script type='text/javascript' src='{$file}'></script>\n";
            }
        }else{
            echo "\t\t<script type='text/javascript' src='/js/". $this->hash .".js'></script>\n";
        }
    }

    /**
     * Método que devuelve el html con la ruta compilada del recurso css
     * @param $debug
     */
    protected function _printCss($debug)
    {
        if($debug)
        {
            if(!empty($this->compiled_files)) foreach($this->compiled_files as $file)
            {
                echo "\t\t<link href='{$file}' rel='stylesheet' media='screen, print'>";
            }
        }else{
            echo "\t\t<link href='/css/". $this->hash .".css' rel='stylesheet' media='screen, print'>";
        }
    }
}