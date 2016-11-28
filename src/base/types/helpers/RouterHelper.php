<?php
namespace PSFS\base\types\helpers;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Router;

/**
 * Class RouterHelper
 * @package PSFS\base\types\helpers
 */
class RouterHelper {

    /**
     * Método que extrae el controller a invocar
     *
     * @param array $action
     *
     * @return Object
     */
    public static function getClassToCall($action)
    {
        Logger::log('Getting class to call for executing the request action', LOG_DEBUG, $action);
        $actionClass = class_exists($action["class"]) ? $action["class"] : "\\" . $action["class"];
        $class = (method_exists($actionClass, "getInstance")) ? $actionClass::getInstance() : new $actionClass;
        return $class;
    }

    /**
     * @param $pattern
     *
     * @return array
     */
    public static function extractHttpRoute($pattern)
    {
        $httpMethod = "ALL";
        $routePattern = $pattern;
        if (FALSE !== strstr($pattern, "#|#")) {
            list($httpMethod, $routePattern) = explode("#|#", $pattern, 2);
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
    public static function extractComponents($route, $pattern)
    {
        Logger::log('Extracting parts for the request to execute');
        $url = parse_url($route);
        $_route = explode("/", $url['path']);
        $_pattern = explode("/", $pattern);
        $get = array();
        if (!empty($_pattern)) foreach ($_pattern as $index => $component) {
            $_get = array();
            preg_match_all('/^\{(.*)\}$/i', $component, $_get);
            if (!empty($_get[1]) && isset($_route[$index])) {
                $get[array_pop($_get[1])] = $_route[$index];
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
    public static function compareSlashes($routePattern, $path)
    {
        $pattern_sep = count(explode('/', $routePattern));
        if (preg_match('/\/$/', $routePattern)) {
            $pattern_sep--;
        }
        $routePattern = preg_replace('/\/\{.*\}$/', '', $routePattern);
        $pattern_sep_clean = count(explode('/', $routePattern));
        if (preg_match('/\/$/', $routePattern)) {
            $pattern_sep_clean--;
        }
        $path_sep = count(explode('/', $path));
        if (preg_match('/\/$/', $path)) {
            $path_sep--;
        }
        return abs($pattern_sep - $path_sep) < 1 || abs($pattern_sep_clean - $path_sep) < 1;
    }

    /**
     * Método que compara la ruta web con la guardada en la cache
     *
     * @param $routePattern
     * @param $path
     *
     * @return bool
     */
    public static function matchRoutePattern($routePattern, $path)
    {
        $expr = preg_replace('/\{([^}]+)\}/', '###', $routePattern);
        $expr = preg_quote($expr, '/');
        $expr = str_replace('###', '(.*)', $expr);
        $expr2 = preg_replace('/\(\.\*\)$/', '', $expr);
        $matched = preg_match('/^' . $expr . '\/?$/i', $path) || preg_match('/^' . $expr2 . '?$/i', $path);
        return $matched;
    }

    /**
     * @param \ReflectionClass $class
     * @param string $domain
     * @return array
     */
    public static function extractDomainInfo(\ReflectionClass $class, $domain)
    {
        $path = dirname($class->getFileName()) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
        $path = realpath($path) . DIRECTORY_SEPARATOR;
        $tpl_path = "templates";
        $public_path = "public";
        $model_path = "models";
        if (!preg_match("/ROOT/", $domain)) {
            $tpl_path = ucfirst($tpl_path);
            $public_path = ucfirst($public_path);
            $model_path = ucfirst($model_path);
        }
        if ($class->hasConstant("TPL")) {
            $tpl_path .= DIRECTORY_SEPARATOR . $class->getConstant("TPL");
        }
        return [
            "template" => $path . $tpl_path,
            "model" => $path . $model_path,
            "public" => $path . $public_path,
        ];
    }

    /**
     * Método que extrae los parámetros de una función
     *
     * @param array $sr
     * @param \ReflectionMethod $method
     *
     * @return array
     */
    public static  function extractReflectionParams($sr, \ReflectionMethod$method)
    {
        $regex = $sr[1] ?: $sr[0];
        $default = '';
        $params = [];
        $parameters = $method->getParameters();
        /** @var \ReflectionParameter $param */
        if (count($parameters) > 0) foreach ($parameters as $param) {
            if ($param->isOptional() && !is_array($param->getDefaultValue())) {
                $params[$param->getName()] = $param->getDefaultValue();
                $default = str_replace('{' . $param->getName() . '}', $param->getDefaultValue(), $regex);
            }
        } else $default = $regex;

        return array($regex, $default, $params);
    }

    /**
     * Método que extrae el método http
     *
     * @param string $docComments
     *
     * @return string
     */
    public static function extractReflectionHttpMethod($docComments)
    {
        preg_match('/@(GET|POST|PUT|DELETE)\n/i', $docComments, $routeMethod);

        return (count($routeMethod) > 0) ? $routeMethod[1] : "ALL";
    }

    /**
     * Método que extrae la visibilidad de una ruta
     *
     * @param string $docComments
     *
     * @return bool
     */
    public static function extractReflectionVisibility($docComments)
    {
        preg_match('/@visible\ (.*)\n/i', $docComments, $visible);
        return !(array_key_exists(1, $visible) && preg_match('/false/i', $visible[1]));
    }

    /**
     * Método que extrae el parámetro de caché
     *
     * @param string $docComments
     *
     * @return bool
     */
    public static function extractReflectionCacheability($docComments)
    {
        preg_match('/@cache\ (.*)\n/i', $docComments, $cache);

        return (count($cache) > 0) ? $cache[1] : "0";
    }

    /**
     * @param string $route
     * @param string $api
     * @return bool
     */
    private static function checkCanAddRoute($route, $api)
    {
        $is_enabled = true;
        // For non api routes
        if(strlen($api) > 0 && preg_match('/admin/i', $route)) {
            // If route is for api, check if admin is enabled in config
            $admin_enabled = Config::getInstance()->get('api.admin');
            if(empty($admin_enabled)) {
                $is_enabled = false;
            }
        }
        return $is_enabled;
    }

    /**
     * @param \ReflectionMethod $method
     * @param string $api
     * @return array
     */
    public static function extractRouteInfo(\ReflectionMethod $method, $api = '')
    {
        $route = $info = null;
        $docComments = $method->getDocComment();
        preg_match('/@route\ (.*)\n/i', $docComments, $sr);
        if (count($sr)) {
            list($regex, $default, $params) = RouterHelper::extractReflectionParams($sr, $method);
            if (strlen($api)) {
                $regex = str_replace('{__API__}', $api, $regex);
                $default = str_replace('{__API__}', $api, $default);
            }
            $httpMethod = RouterHelper::extractReflectionHttpMethod($docComments);
            if(self::checkCanAddRoute($regex, $api)) {
                $route = $httpMethod . "#|#" . $regex;
                $info = [
                    "method" => $method->getName(),
                    "params" => $params,
                    "default" => $default,
                    "visible" => RouterHelper::extractReflectionVisibility($docComments),
                    "http" => $httpMethod,
                    "cache" => RouterHelper::extractReflectionCacheability($docComments),
                ];
            }
        }
        return [$route, $info];
    }

    /**
     * @param string $route
     * @return null|string
     */
    public static function checkDefaultRoute($route)
    {
        $default = null;
        if (FALSE !== preg_match('/\/$/', $route)) {
            $default = Config::getInstance()->get('home_action');
        } elseif (false !== preg_match('/admin/', $route)) {
            $default = Config::getInstance()->get('admin_action') ?: 'admin-login';

        }
        if(null !== $default) {
            return Router::getInstance()->execute(Router::getInstance()->getRoute($default));
        }
        return null;
    }
}