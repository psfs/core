<?php

namespace PSFS\base\extension;

use PSFS\base\config\Config;
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
     * @param $string
     * @param null|string $name
     * @param bool $return
     * @return string|null
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function asset($string, $name = null, $return = true)
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
     * @param bool|FALSE $absolute
     * @param array $params
     *
     * @return string|null
     */
    public static function route($path = '', $absolute = false, array $params = [])
    {
        $router = Router::getInstance();
        try {
            return $router->getRoute($path, $absolute, $params);
        } catch (\Exception $e) {
            return $router->getRoute('', $absolute, $params);
        }
    }

    /**
     * Función que devuelve un parámetro de la configuración
     * @param $param
     * @param string $default
     *
     * @return string
     */
    public static function config($param, $default = '')
    {
        return Config::getInstance()->get($param) ?: $default;
    }

    /**
     * Función que devuelve un query string
     * @param string $query
     *
     * @return string
     */
    public static function query($query)
    {
        return Request::getInstance()->getQuery($query);
    }

    /**
     * @param array $button
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public static function button(array $button)
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
    public static function widget(array $field, $label = null)
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
    public static function form(Form $form)
    {
        Template::getInstance()->getTemplateEngine()->display('forms/base.html.twig', array(
            'form' => $form,
        ));
    }

    /**
     * Función que copia un recurso directamente en el DocumentRoot
     * @param string $path
     * @param string $dest
     * @param bool|FALSE $force
     *
     * @return string
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function resource($path, $dest, $force = false)
    {
        $debug = Config::getParam('debug');
        $domains = Template::getDomains(true);
        $filenamePath = self::extractPathname($path, $domains);
        GeneratorHelper::copyResources($dest, $force, $filenamePath, $debug);
        return '';
    }

    /**
     * Método que extrae el pathname para un dominio
     * @param string $path
     * @param $domains
     *
     * @return mixed
     */
    private static function extractPathname($path, $domains)
    {
        $filenamePath = $path;
        if (!empty($domains) && !file_exists($path)) {
            foreach ($domains as $domain => $paths) {
                $domainFilename = str_replace($domain, $paths['public'], $path);
                if (file_exists($domainFilename)) {
                    $filenamePath = $domainFilename;
                    continue;
                }
            }

        }

        return $filenamePath;
    }

    /**
     * @param $filenamePath
     * @throws \PSFS\base\exception\GeneratorException
     */
    private static function processCssLines($filenamePath)
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
     * @param string $name
     * @param string $filenamePath
     * @param string $base
     * @param string $filePath
     */
    private static function putResourceContent($name, $filenamePath, $base, $filePath)
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
     * @param string $name
     * @param boolean $return
     * @param string $filenamePath
     * @return mixed
     * @throws \PSFS\base\exception\GeneratorException
     */
    private static function processAsset($string, $name, $return, $filenamePath)
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
    public static function session($key)
    {
        return Security::getInstance()->getSessionKey($key);
    }

    /**
     * Template function that check if exists any flash session var
     * @param string $key
     * @return bool
     */
    public static function existsFlash($key = '')
    {
        return null !== Security::getInstance()->getFlash($key);
    }

    /**
     * Template function that get a flash session var
     * @param string $key
     * @return mixed
     */
    public static function getFlash($key)
    {
        $var = Security::getInstance()->getFlash($key);
        Security::getInstance()->setFlash($key, null);
        return $var;
    }

}
