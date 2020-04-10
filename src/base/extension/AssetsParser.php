<?php

namespace PSFS\base\extension;

use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\extension\traits\CssTrait;
use PSFS\base\extension\traits\JsTrait;
use PSFS\base\Request;
use PSFS\base\Template;

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
        switch ($this->type) {
            default:
            case "js":
                $this->printJs($this->compiledFiles, $baseUrl, $this->hash);
                break;
            case "css":
                $this->printCss($this->compiledFiles, $baseUrl, $this->hash);
                break;
        }
    }

}
