<?php

namespace PSFS\base;

use PSFS\base\Singleton;
use PSFS\config\Config;
use PSFS\exception\ConfigException;
use PSFS\config\AdminForm;
use PSFS\base\Security;

/**
 * Class Router
 * @package PSFS
 */
class Router extends Singleton{

    protected $routing;
    protected $slugs;

    function __construct()
    {
        if(Config::getInstance()->getDebugMode() || !file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json"))
        {
            $this->hydrateRouting();
        }else $this->routing = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json"), true);
        $this->simpatize();
    }

    /**
     * Método que deriva un error HTTP de página no encontrada
     */
    public function httpNotFound()
    {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    /**
     * Método que calcula el objeto a enrutar
     * @param $route
     *
     * @return bool
     */
    public function execute($route)
    {
        //Chequeamos si entramos en el admin
        if(preg_match("/^\/admin/i", $route))
        {
            if(!Security::getInstance()->checkAdmin())
            {
                header('HTTP/1.1 401 Unauthorized');
                header('WWW-Authenticate: Basic Realm="PSFS"');
                echo _("Es necesario ser administrador para ver ésta zona");
                exit();
            }
        }
        //Restricción de la web por contraseña
        if(!preg_match("/^\/(admin|setup\-admin)/i", $route) && null !== Config::getInstance()->get('restricted'))
        {
            if(!Security::getInstance()->checkAdmin())
            {
                header('HTTP/1.1 401 Unauthorized');
                header('WWW-Authenticate: Basic Realm="Zona Restringida"');
                echo _("Espacio web restringido");
                exit();
            }
        }

        //Revisamos si tenemos la ruta registrada
        foreach($this->routing as $pattern => $action)
        {
            if(preg_match("/^".preg_quote($pattern, "/")."$/i", $route))
            {
                /** @var $class PSFS\types\Controller */
                $class = (method_exists($action["class"], "getInstance")) ? $action["class"]::getInstance() : new $action["class"];
                return call_user_func_array(array($class, $action["method"]), array());
            }
        }

        if(preg_match("/\/$/", $route)) return $this->execute(substr($route, 0, strlen($route) -1));

        return false;
    }

    /**
     * Método que devuelve el namespace asociado a una clase
     * @param $class
     *
     * @return null
     */
    protected function findClass($class)
    {
        //Buscamos en PSFS en primera instancia
        $class_namespace = $this->exploreDir(LIB_DIR . DIRECTORY_SEPARATOR . 'psfs' . DIRECTORY_SEPARATOR . 'src', $class);
        //Si no tenemos la ruta buscamos en la carpeta de módulos
        if(is_null($class_namespace))
        {
            $class_namespace = $this->exploreDir(BASE_DIR . DIRECTORY_SEPARATOR . 'modules', $class, 'Modules');
        }
        return $class_namespace;
    }

    /**
     * Método que busca en la estructura de directorios la clase
     * @param $dir
     * @param $class
     * @param $base
     * @return null
     */
    protected function exploreDir($orig_dir, $class, $base = 'PSFS')
    {
        $class_namespace = null;
        if(file_exists($orig_dir. DIRECTORY_SEPARATOR . $class . ".php"))
        {
            $class_namespace = $base . "\\" . $class;
        }elseif(is_dir($orig_dir))
        {
            $d = dir($orig_dir);
            while(false !== ($dir = $d->read()))
            {
                if(is_dir($orig_dir.DIRECTORY_SEPARATOR.$dir) && preg_match("/^\./",$dir) == 0)
                {
                    $class_namespace = $this->exploreDir($orig_dir.DIRECTORY_SEPARATOR.$dir, $class, $base . "\\" . $dir);
                    if(!is_null($class_namespace)) break;
                }
            }
        }
        return $class_namespace;
    }

    /**
     * Método que regenera el fichero de rutas
     */
    private function hydrateRouting()
    {
        $base = LIB_DIR . DIRECTORY_SEPARATOR . 'psfs' . DIRECTORY_SEPARATOR . 'src';
        $modules = BASE_DIR . DIRECTORY_SEPARATOR . 'modules';
        $this->routing = $this->inspectDir($base, "PSFS", array());
        $this->routing = $this->inspectDir($modules, "", $this->routing);
        $home = Config::getInstance()->get('home_action');
        if(!empty($home))
        {
            $home_params = null;
            foreach($this->routing as $pattern => $params)
            {
                if(preg_match("/".preg_quote($pattern, "/")."$/i", "/".$home)) $home_params = $params;
            }
            if(!empty($home_params)) $this->routing['/'] = $home_params;
        }
        file_put_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json", json_encode($this->routing));
    }

    /**
     * Método que inspecciona los directorios en busca de clases que registren rutas
     * @param $dir
     * @param $routing
     *
     * @return mixed
     */
    private function inspectDir($origen, $namespace = "PSFS", $routing)
    {
        $d = dir($origen);
        while(false !== ($dir = $d->read()))
        {
            if(is_dir($origen.DIRECTORY_SEPARATOR.$dir) && preg_match("/^\./",$dir) == 0)
            {
                $routing = $this->inspectDir($origen.DIRECTORY_SEPARATOR.$dir, $namespace . '\\' . $dir, $routing);
            }elseif(preg_match("/\.php$/",$dir)){
                $routing = $this->addRouting($namespace . '\\' .str_replace(".php", "", $dir), $routing);
            }
        }
        return $routing;
    }

    /**
     * Método que añade nuevas rutas al array de referencia
     * @param $routing
     *
     * @return mixed
     */
    private function addRouting($namespace, $routing)
    {
        if(class_exists($namespace))
        {
            $reflection = new \ReflectionClass($namespace);
            if(false === $reflection->isAbstract() && false === $reflection->isInterface())
            {
                foreach($reflection->getMethods() as $method)
                {
                    if($method->isPublic())
                    {
                        preg_match("/@route\ (.*)\n/i", $method->getDocComment(), $sr);
                        if(count($sr))
                        {
                            $regex = $sr[1] ?: $sr[0];
                            $routing[$regex] = array(
                                "class" => $namespace,
                                "method" => $method->getName(),
                                "params" => $method->getParameters(),
                            );
                        }
                    }
                }
            }
        }
        return $routing;
    }

    /**
     * Método que genera las urls amigables para usar dentro del framework
     * @return $this
     */
    private function simpatize()
    {
        foreach($this->routing as $key => $info)
        {
            $this->slugs[$this->slugify($key)] = $key;
        }
        return $this;
    }

    /**
     * Método que devuelve el slug de un string dado
     * @param $text
     *
     * @return mixed|string
     */
    private function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);

        // trim
        $text = trim($text, '-');

        // transliterate
        if (function_exists('iconv'))
        {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }

        // lowercase
        $text = strtolower($text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text))
        {
            return 'n-a';
        }

        return $text;
    }

    /**
     * Método que devuelve una ruta del framework
     * @param $slug
     *
     * @return mixed
     * @throws \PSFS\exception\ConfigException
     */
    public function getRoute($slug = '')
    {
        if(strlen($slug) == 0) return '/';
        if(!isset($this->slugs[$slug])) throw new ConfigException("No existe la ruta especificada");
        return $this->slugs[$slug];
    }

    /**
     * Método que pinta por pantalla todas las rutas del sistema
     * @route /admin/routes
     */
    public function printRoutes()
    {
        return Template::getInstance()->render('routing.html.twig', array(
            'routes' => $this->slugs,
        ));
    }
}