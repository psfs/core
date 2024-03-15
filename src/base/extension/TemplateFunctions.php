<?php

namespace PSFS\base\extension;

use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\exception\GeneratorException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\base\types\Form;
use PSFS\base\types\helpers\AssetsHelper;
use PSFS\base\types\helpers\GeneratorHelper;

/**
 * Class TemplateFunctions
 * @package PSFS\base\extension
 */
class TemplateFunctions
{

    const ASSETS_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::asset';
    const ROUTE_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::route';
    const CONFIG_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::config';
    const BUTTON_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::button';
    const WIDGET_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::widget';
    const FORM_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::form';
    const RESOURCE_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::resource';
    const SESSION_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::session';
    const EXISTS_FLASH_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::existsFlash';
    const GET_FLASH_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::getFlash';
    const GET_QUERY_FUNCTION = '\\PSFS\\base\\extension\\TemplateFunctions::query';

    /**
     * Función que copia los recursos de las carpetas Public al DocumentRoot
     * @param string $string
     * @param string|null $name
     * @param bool $return
     * @return string|null
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function asset(string $string, string $name = null, bool $return = true): ?string
    {

        $filePath = '';
        if (!file_exists($filePath)) {
            $filePath = BASE_DIR . $string;
        }
        $filenamePath = AssetsHelper::findDomainPath($string, $filePath);

        $filePath = self::processAsset($string, $name, $return, $filenamePath);
        $basePath = Config::getParam('resources.cdn.url', Request::getInstance()->getRootUrl());
        $returnPath = empty($name) ? $basePath . '/' . $filePath : $name;
        return $return ? $returnPath : '';
    }

    /**
     * Función que devuelve una url correspondiente a una ruta
     * @param string $path
     * @param bool $absolute
     * @param array $params
     *
     * @return string|null
     */
    public static function route(string $path = '', bool $absolute = false, array $params = []): ?string
    {
        $router = Router::getInstance();
        try {
            return $router->getRoute($path, $absolute, $params);
        } catch (\Exception $e) {
            Logger::log($e->getMessage());
            return $router->getRoute('', $absolute, $params);
        }
    }

    /**
     * Función que devuelve un parámetro de la configuración
     * @param string $param
     * @param string $default
     *
     * @return mixed|null
     */
    public static function config(string $param, string $default = ''): mixed
    {
        return Config::getInstance()->get($param) ?: $default;
    }

    /**
     * Función que devuelve un query string
     * @param string $query
     *
     * @return string
     */
    public static function query(string $query): string
    {
        return Request::getInstance()->getQuery($query);
    }

    /**
     * @param array $button
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function button(array $button): void
    {
        Template::getInstance()->getTemplateEngine()->display('forms/button.html.twig', array(
            'button' => $button,
        ));
    }

    /**
     * @param array $field
     * @param string|null $label
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function widget(array $field, string $label = null): void
    {
        if (null !== $label) {
            $field['label'] = $label;
        }
        //Limpiamos los campos obligatorios
        if (!isset($field['required'])) {
            $field['required'] = true;
        }
        if (isset($field['required']) && (bool)$field['required'] === false) {
            unset($field['required']);
        }
        Template::getInstance()->getTemplateEngine()->display('forms/field.html.twig', array(
            'field' => $field,
        ));
    }

    /**
     * Función que deveulve un formulario en html
     * @param Form $form
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function form(Form $form): void
    {
        Template::getInstance()->getTemplateEngine()->display('forms/base.html.twig', array(
            'form' => $form,
        ));
    }

    /**
     * Función que copia un recurso directamente en el DocumentRoot
     * @param string $path
     * @param string $dest
     * @param bool|bool $force
     *
     * @return string
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function resource(string $path, string $dest, bool $force = false): string
    {
        $debug = Config::getParam('debug');
        $domains = Template::getDomains(true);
        $filenamePath = self::extractPathname($path, $domains);
        // Check if resources has been copied to public folders
        if (!$debug) {
            $cacheFilename = Config::getParam('cache.var', '__initial__') . '.file.cache';
            $cachedFiles = Cache::getInstance()->readFromCache($cacheFilename,
                1, fn() => [], Cache::JSON, true) ?: [];
            // Force the resource copy
            if (!in_array($filenamePath, $cachedFiles) || $force) {
                $force = true;
                $cachedFiles[] = $filenamePath;
                Cache::getInstance()->storeData($cacheFilename, $cachedFiles, Cache::JSON);
            }
        }
        GeneratorHelper::copyResources($dest, $force, $filenamePath, $debug);
        return '';
    }

    /**
     * Método que extrae el pathname para un dominio
     * @param string $path
     * @param $domains
     *
     * @return string|array
     */
    private static function extractPathname(string $path, $domains): string|array
    {
        $filenamePath = $path;
        if (!empty($domains) && !file_exists($path)) {
            foreach ($domains as $domain => $paths) {
                $domainFilename = str_replace($domain, $paths['public'], $path);
                if (file_exists($domainFilename)) {
                    $filenamePath = $domainFilename;
                    break;
                }
            }

        }

        return $filenamePath;
    }

    /**
     * @param $filenamePath
     * @throws \PSFS\base\exception\GeneratorException
     */
    private static function processCssLines($filenamePath): void
    {
        $handle = @fopen($filenamePath, 'r');
        if ($handle) {
            while (!feof($handle)) {
                AssetsParser::extractCssLineResource($handle, $filenamePath);
            }
            fclose($handle);
        }
    }

    /**
     * Método que copia el contenido de un recurso en su destino correspondiente
     * @param string|null $name
     * @param string $filenamePath
     * @param string $base
     * @param string $filePath
     */
    private static function putResourceContent(string|null $name, string $filenamePath, string $base, string $filePath): void
    {
        $data = file_get_contents($filenamePath);
        if (!empty($name)) {
            file_put_contents(WEB_DIR . DIRECTORY_SEPARATOR . $name, $data);
        } else {
            file_put_contents($base . $filePath, $data);
        }
    }

    /**
     * Método que procesa un recurso para su copia en el DocumentRoot
     * @param string $string
     * @param string|null $name
     * @param boolean $return
     * @param string $filenamePath
     * @return string
     * @throws GeneratorException
     */
    private static function processAsset(string $string, string|null $name = null, bool $return = true, string $filenamePath = ''): string
    {
        $filePath = $filenamePath;
        if (file_exists($filenamePath)) {
            list($base, $htmlBase, $filePath) = AssetsHelper::calculateAssetPath($string, $name, $return, $filenamePath);
            //Creamos el directorio si no existe
            GeneratorHelper::createDir($base . $htmlBase);
            //Si se ha modificado
            if (!file_exists($base . $filePath) || filemtime($base . $filePath) < filemtime($filenamePath)) {
                if ($htmlBase === 'css') {
                    self::processCssLines($filenamePath);
                }
                self::putResourceContent($name, $filenamePath, $base, $filePath);
            }
        }

        return $filePath;
    }

    /**
     * Template function for get a session var
     * @param string $key
     * @return mixed
     */
    public static function session(string $key): mixed
    {
        return Security::getInstance()->getSessionKey($key);
    }

    /**
     * Template function that check if exists any flash session var
     * @param string $key
     * @return bool
     */
    public static function existsFlash(string $key = ''): bool
    {
        return null !== Security::getInstance()->getFlash($key);
    }

    /**
     * Template function that get a flash session var
     * @param string $key
     * @return mixed
     */
    public static function getFlash(string $key): mixed
    {
        $var = Security::getInstance()->getFlash($key);
        Security::getInstance()->setFlash($key, null);
        return $var;
    }

}
