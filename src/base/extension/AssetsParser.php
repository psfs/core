<?php

namespace PSFS\base\extension;

use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\extension\traits\CssTrait;
use PSFS\base\extension\traits\JsTrait;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Template;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\Inspector;

defined('CSS_SRI_FILENAME') or define('CSS_SRI_FILENAME', CACHE_DIR . DIRECTORY_SEPARATOR . 'css.sri.json');
defined('JS_SRI_FILENAME') or define('JS_SRI_FILENAME', CACHE_DIR . DIRECTORY_SEPARATOR . 'js.sri.json');
/**
 * Class AssetsParser
 * @package PSFS\base\extension
 */
class AssetsParser
{
    use CssTrait;
    use JsTrait;

    /**
     * @var array
     */
    protected $files = [];
    /**
     * @var string
     */
    protected $hash;
    /**
     * @var array
     */
    protected $compiledFiles = [];
    /**
     * @var string
     */
    protected $type;
    /**
     * @var array
     */
    protected $domains = [];
    /**
     * @var string
     */
    private $cdnPath = null;
    /**
     * @var array
     */
    protected $sri = [];

    /**
     * @var string
     */
    protected $sriFilename;

    /**
     * @param string $type
     */
    public function init($type) {
        $this->sriFilename = $type === 'js' ? JS_SRI_FILENAME : CSS_SRI_FILENAME;
        /** @var Cache $cache */
        $cache = Cache::getInstance();
        $this->sri = $cache->getDataFromFile($this->sriFilename, Cache::JSON, true);
        if(empty($this->sri)) {
            $this->sri = [];
        }
    }

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
        $this->cdnPath = Config::getParam('resources.cdn.url', Request::getInstance()->getRootUrl());
    }

    /**
     * Método que añade un nuevo fichero al proceso de generación de los assets
     * @param $filename
     * @return AssetsParser
     * @internal param string $type
     */
    public function addFile($filename)
    {
        if (file_exists($this->path . $filename) && preg_match('/\.' . $this->type . '$/i', $filename)) {
            $this->files[] = $filename;
        } elseif (!empty($this->domains)) {
            foreach ($this->domains as $domain => $paths) {
                $domainFilename = str_replace($domain, $paths["public"], $filename);
                if (file_exists($domainFilename) && preg_match('/\.' . $this->type . '$/i', $domainFilename)) {
                    $this->files[] = $domainFilename;
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
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function compile()
    {
        //Unificamos ficheros para que no se retarde mucho el proceso
        $this->files = array_unique($this->files);
        switch ($this->type) {
            default:
            case "js":
                $this->compileJs($this->files, $this->path, $this->hash, $this->compiledFiles);
                break;
            case "css":
                $this->compileCss($this->path, $this->hash);
                break;
        }

        return $this;
    }

    /**
     * Método que imprime el resultado de la generación de los assets
     */
    public function printHtml()
    {
        $baseUrl = $this->cdnPath ?: '';
        $sri = ''; //Config::getParam('debug') ? '' : $this->getSriHash($this->hash, $this->type);
        switch ($this->type) {
            default:
            case "js":
                $this->printJs($this->compiledFiles, $baseUrl, $this->hash, $sri);
                break;
            case "css":
                $this->printCss($this->compiledFiles, $baseUrl, $this->hash, $sri);
                break;
        }
    }

    /**
     * Método que calcula el path completo a copiar un recurso
     * @param string $filenamePath
     * @param string[] $source
     * @return string
     */
    protected static function calculateResourcePathname($filenamePath, $source)
    {
        $sourceFile = preg_replace("/'/", "", $source[1]);
        if (preg_match('/\#/', $sourceFile)) {
            $sourceFile = explode("#", $sourceFile);
            $sourceFile = $sourceFile[0];
        }
        if (preg_match('/\?/', $sourceFile)) {
            $sourceFile = explode("?", $sourceFile);
            $sourceFile = $sourceFile[0];
        }
        $orig = realpath(dirname($filenamePath) . DIRECTORY_SEPARATOR . $sourceFile);
        return $orig;
    }

    /**
     * Método que extrae el recurso css de una línea de estilos css
     * @param $handle
     * @param string $filenamePath
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function extractCssLineResource($handle, $filenamePath)
    {
        $line = fgets($handle);
        $urls = array();
        if (preg_match_all('#url\((.*?)\)#', $line, $urls, PREG_SET_ORDER)) {
            foreach ($urls as $source) {
                $orig = self::calculateResourcePathname($filenamePath, $source);
                if(!empty($orig)) {
                    $orig_part = preg_split("/Public/i", $orig);
                    $dest = WEB_DIR . $orig_part[1];
                    GeneratorHelper::createDir(dirname($dest));
                    if (@copy($orig, $dest) === false) {
                        throw new ConfigException("Can't copy " . $orig . " to " . $dest);
                    }
                } else {
                    Logger::log($filenamePath . ' has an empty origin with the url ' . $source, LOG_WARNING);
                }
            }
        }
    }

    /**
     * @param $hash
     * @param string $type
     * @return mixed|string
     * @throws \PSFS\base\exception\GeneratorException
     */
    protected function getSriHash($hash, $type = 'js') {
        if(array_key_exists($hash, $this->sri)) {
            $sriHash = $this->sri[$hash];
        } else {
            Inspector::stats('[SRITrait] Generating SRI for ' . $hash, Inspector::SCOPE_DEBUG);
            $filename = WEB_DIR . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $hash . '.' . $type;
            $sriHash = base64_encode(hash("sha384", file_get_contents($filename), true));
            $this->sri[$hash] = $sriHash;
            Cache::getInstance()->storeData($this->sriFilename, $this->sri, Cache::JSON, true);
        }
        return $sriHash;
    }

}
