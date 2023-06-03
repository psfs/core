<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\Template;

/**
 * Class AssetsHelper
 * @package PSFS\base\types\helpers
 */
class AssetsHelper
{

    /**
     * @param string $source
     * @return string
     */
    public static function extractSourceFilename($source)
    {
        $sourceFile = preg_replace("/'/", "", $source[1]);
        if (preg_match('/\#/', $sourceFile)) {
            $sourceFile = explode("#", $sourceFile);
            $sourceFile = $sourceFile[0];
        }
        if (preg_match('/\?/', $sourceFile)) {
            $sourceFile = explode("?", $sourceFile);
            $sourceFile = $sourceFile[0];
            return $sourceFile;
        }
        return $sourceFile;
    }

    /**
     * Servicio que busca el path para un dominio dado
     * @param $string
     * @param string $filePath
     *
     * @return string
     */
    public static function findDomainPath($string, $filePath)
    {
        $domains = Template::getDomains(TRUE);
        $filenamePath = null;
        if (!file_exists($filePath) && 0 < count($domains)) {
            foreach ($domains as $domain => $paths) {
                $domainFilename = str_replace($domain, $paths["public"], $string);
                if (file_exists($domainFilename)) {
                    $filenamePath = $domainFilename;
                    continue;
                }
            }
        }

        return $filenamePath;
    }

    /**
     * Método que calcula el path de un recurso web
     * @param string $string
     * @param string $name
     * @param boolean $return
     * @param string $filenamePath
     *
     * @return string[]
     */
    public static function calculateAssetPath($string, $name, $return, $filenamePath)
    {
        $path = explode("/", $string);
        $originalFilename = end($path);
        $base = WEB_DIR . DIRECTORY_SEPARATOR;
        $debug = Config::getParam('debug');
        $cache = Config::getParam('cache.var');
        $cache = $cache ? '.' . $cache : '';
        $finfo = finfo_open(FILEINFO_MIME_TYPE); // devuelve el tipo mime de su extensión
        $mime = finfo_file($finfo, $filenamePath);
        $extension = explode(".", $string);
        $extension = end($extension);
        $file = "/" . substr(md5($string), 0, 8) . "." . $extension;
        $htmlBase = '';
        finfo_close($finfo);
        if (preg_match('/\.css$/i', $string)) {
            $file = "/" . substr(md5($string), 0, 8) . "$cache.css";
            $htmlBase = "css";
        } elseif (preg_match('/\.js$/i', $string)) {
            $file = "/" . substr(md5($string), 0, 8) . "$cache.js";
            $htmlBase = "js";
        } elseif (preg_match("/image/i", $mime)) {
            $htmlBase = "img";
        } elseif (preg_match("/(doc|pdf)/i", $mime)) {
            $htmlBase = "docs";
        } elseif (preg_match("/(video|audio|ogg)/i", $mime)) {
            $htmlBase = "media";
        } elseif (preg_match("/(text|html)/i", $mime)) {
            $htmlBase = "templates";
        } elseif (!$return && !is_null($name)) {
            $file = $name;
        }
        if ($debug) {
            $file = str_replace("." . $extension, "_" . $originalFilename, $file);
        }
        $filePath = $htmlBase . $file;

        return array($base, $htmlBase, $filePath);
    }

    /**
     * Método para guardar cualquier contenido y controlar que existe el directorio y se guarda correctamente
     * @param string $path
     * @param string $content
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function storeContents($path, $content = "")
    {
        GeneratorHelper::createDir(dirname($path));
        if ("" !== $content && false === file_put_contents($path, $content)) {
            throw new ConfigException(t('No se tienen permisos para escribir en ' . $path));
        }
    }
}
