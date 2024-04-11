<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Class RouterHelper
 * @package PSFS\base\types\helpers
 */
class RouterHelper
{
    /**
     * @param array $action
     * @return mixed
     * @throws ReflectionException
     */
    public static function getClassToCall(array $action): mixed
    {
        Inspector::stats('[RouterHelper] Getting class to call for executing the request action', Inspector::SCOPE_DEBUG);
        Logger::log('Getting class to call for executing the request action', LOG_DEBUG, $action);
        $actionClass = class_exists($action['class']) ? $action['class'] : "\\" . $action['class'];
        $reflectionClass = new ReflectionClass($actionClass);
        if ($reflectionClass->hasMethod('getInstance')) {
            $class = $reflectionClass->getMethod('getInstance')->invoke(null, $action['method']);
        } else {
            $class = new $actionClass;
        }
        return $class;
    }

    /**
     * @param $pattern
     *
     * @return array
     */
    public static function extractHttpRoute($pattern): array
    {
        $httpMethod = 'ALL';
        $routePattern = $pattern;
        if (str_contains($pattern, '#|#')) {
            list($httpMethod, $routePattern) = explode('#|#', $pattern, 2);
        }

        return array(strtoupper($httpMethod), $routePattern);
    }

    /**
     * Método que extrae de la url los parámetros REST
     *
     * @param string $route
     *
     * @param string $pattern
     *
     * @return array
     */
    public static function extractComponents(string $route, string $pattern): array
    {
        Inspector::stats('[RouterHelper] Extracting parts for the request to execute', Inspector::SCOPE_DEBUG);
        if (Config::getParam('allow.double.slashes', true)) {
            $route = preg_replace("/\/\//", '/', $route);
        }
        $url = parse_url($route);
        $partialRoute = explode('/', $url['path']);
        $partialPattern = explode('/', $pattern);
        $get = [];
        if (!empty($partialPattern)) {
            foreach ($partialPattern as $index => $component) {
                $query = [];
                preg_match_all('/^\{(.*)\}$/', $component, $query);
                if (!empty($query[1]) && isset($partialRoute[$index])) {
                    $get[array_pop($query[1])] = $partialRoute[$index];
                }
            }
        }

        return $get;
    }

    /**
     * Function that checks if the long of the patterns match
     * @param $routePattern
     * @param $path
     * @return bool
     */
    public static function compareSlashes($routePattern, $path): bool
    {
        $patternSeparator = count(explode('/', $routePattern));
        if (preg_match('/\/$/', $routePattern)) {
            $patternSeparator--;
        }
        $routePattern = preg_replace('/\/\{.*\}$/', '', $routePattern);
        $cleanPatternSep = count(explode('/', $routePattern));
        if (preg_match('/\/$/', $routePattern)) {
            $cleanPatternSep--;
        }

        if (Config::getParam('allow.double.slashes', true)) {
            $path = preg_replace("/\/\//", '/', $path);
        }
        $pathSep = count(explode('/', $path));
        if (preg_match('/\/$/', $path)) {
            $pathSep--;
        }
        return abs($patternSeparator - $pathSep) < 1 || abs($cleanPatternSep - $pathSep) < 1;
    }

    /**
     * Método que compara la ruta web con la guardada en la cache
     *
     * @param $routePattern
     * @param $path
     *
     * @return bool
     */
    public static function matchRoutePattern($routePattern, $path): bool
    {
        if (Config::getParam('allow.double.slashes', true)) {
            $path = preg_replace("/\/\//", '/', $path);
        }
        $expr = preg_replace('/\{([^}]+)\}/', '%%%', $routePattern);
        $expr = preg_quote($expr, '/');
        $expr = str_replace('%%%', '(.*)', $expr);
        $expr2 = preg_replace('/\(\.\*\)$/', '', $expr);
        $matched = preg_match('/^' . $expr . '\/?$/i', $path) || preg_match('/^' . $expr2 . '?$/i', $path);
        return $matched;
    }

    /**
     * @param ReflectionClass $class
     * @param string $domain
     * @return array
     */
    public static function extractDomainInfo(ReflectionClass $class, string $domain): array
    {
        $path = dirname($class->getFileName()) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
        $templatesPath = 'templates';
        $publicPath = 'public';
        $modelsPath = 'models';
        if (!str_contains($domain, 'ROOT')) {
            $templatesPath = ucfirst($templatesPath);
            $publicPath = ucfirst($publicPath);
            $modelsPath = ucfirst($modelsPath);
        }
        if ($class->hasConstant('TPL')) {
            $templatesPath .= DIRECTORY_SEPARATOR . $class->getConstant('TPL');
        }
        return [
            'base' => $path,
            'template' => $path . $templatesPath,
            'model' => $path . $modelsPath,
            'public' => $path . $publicPath,
        ];
    }

    /**
     * @param $regex
     * @param ReflectionMethod $method
     * @return array
     * @throws ReflectionException
     */
    public static function extractReflectionParams($regex, ReflectionMethod $method): array
    {
        $default = '';
        $params = [];
        $parameters = $method->getParameters();
        $requirements = [];
        /** @var ReflectionParameter $param */
        if (count($parameters) > 0) {
            foreach ($parameters as $param) {
                if ($param->isOptional() && !is_array($param->getDefaultValue())) {
                    $params[$param->getName()] = $param->getDefaultValue();
                    $default = str_replace('{' . $param->getName() . '}', $param->getDefaultValue() ?: '', $regex ?: '');
                } elseif (!$param->isOptional()) {
                    $requirements[] = $param->getName();
                }
            }
        } else {
            $default = $regex;
        }

        return [$regex, $default, $params, $requirements];
    }

    /**
     * @param ReflectionMethod $method
     * @param string $api
     * @param string $module
     * @return array
     * @throws ReflectionException
     */
    public static function extractRouteInfo(ReflectionMethod $method, string $api = '', string $module = ''): array
    {
        $route = $info = null;
        $docComments = $method->getDocComment();
        $regexpRoute = AnnotationHelper::extractRoute($docComments);
        if (null !== $regexpRoute) {
            list($regex, $default, $params, $requirements) = RouterHelper::extractReflectionParams($regexpRoute, $method);
            $originalRegex = $regex;
            if ('' !== $api && str_contains($regex, '__API__')) {
                $regex = str_replace('{__API__}', $api, $regex);
                $default = str_replace('{__API__}', $api, $default);
            }
            $regex = str_replace('{__DOMAIN__}', $module, $regex);
            $default = str_replace('{__DOMAIN__}', $module, $default);
            $httpMethod = AnnotationHelper::extractReflectionHttpMethod($docComments);
            $icon = AnnotationHelper::extractDocIcon($docComments);
            $label = AnnotationHelper::extractReflectionLabel(str_replace('{__API__}', $api, $docComments));
            $route = $httpMethod . "#|#" . $regex;
            $route = preg_replace('/(\\r|\\f|\\t|\\n)/', '', $route);
            $info = [
                'method' => $method->getName(),
                'params' => $params,
                'default' => $default,
                'pattern' => $originalRegex,
                'label' => $label,
                'icon' => strlen($icon) > 0 ? $icon : '',
                'module' => preg_replace('/(\\\|\\/)/', '', $module),
                'visible' => AnnotationHelper::extractReflectionVisibility($docComments),
                'http' => $httpMethod,
                'cache' => AnnotationHelper::extractReflectionCacheability($docComments),
                'requirements' => $requirements,
            ];
        }
        return [$route, $info];
    }

    /**
     * Método que devuelve el slug de un string dado
     *
     * @param string $text
     *
     * @return string
     */
    public static function slugify(string $text): string
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);

        // trim
        $text = trim($text, '-');

        // transliterate
        if (function_exists('iconv') && extension_loaded('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }

        // lowercase
        $text = strtolower($text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

}
