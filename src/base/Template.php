<?php

namespace PSFS\Base;

use PSFS\base\Singleton;
use PSFS\config\Config;
use PSFS\Dispatcher;
use PSFS\base\Router;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\extension\AssetsTokenParser;

class Template extends Singleton{

    protected $tpl;
    protected $filters = array();

    protected $debug = false;
    protected $public_zone = true;
    private $status_code = null;

    function __construct()
    {
        $this->debug = Config::getInstance()->getDebugMode() ?: false;
        $loader = new \Twig_Loader_Filesystem(Config::getInstance()->getTemplatePath());
        $this->tpl = new \Twig_Environment($loader, array(
            'cache' => Config::getInstance()->getCachePath(),
            'debug' => (bool)$this->debug,
        ));
        //Asignamos las funciones especiales
        $this->addAssetFunction()
            ->addFormsFunction()
            ->addFormWidgetFunction()
            ->addFormButtonFunction()
            ->addConfigFunction()
            ->addTranslationFilter()
            ->addRouteFunction()
            ->dumpResource();

        //Añadimos las extensiones de los tags
        $this->tpl->addTokenParser(new AssetsTokenParser("css"));
        $this->tpl->addTokenParser(new AssetsTokenParser("js"));

        //Optimizamos
        $this->tpl->addExtension(new \Twig_Extension_Optimizer());
    }

    /**
     * Método que activa la zona pública
     * @param bool $public
     *
     * @return $this
     */
    public function setPublicZone($public = true)
    {
        $this->public_zone = $public;
        return $this;
    }

    /**
     * Método que establece un header de http status code
     * @param null $status
     *
     * @return $this
     */
    public function setStatus($status = null)
    {
        switch($status)
        {
            case '500': $this->status_code = "HTTP/1.0 500 Internal Server Error"; break;
            case '404': $this->status_code = "HTTP/1.0 404 Not Found"; break;
            case '403': $this->status_code = "HTTP/1.0 403 Forbidden"; break;
        }
        return $this;
    }

    /**
     * Método que procesa la plantilla
     * @param $tpl
     * @param array $vars
     * @return mixed
     */
    public function render($tpl, array $vars = array(), $cookies = array())
    {
        ob_start();
        header("X-Powered-By: @c15k0");
        if($this->debug)
        {
            $vars["__DEBUG__"]["includes"] = get_included_files();
            $vars["__DEBUG__"]["trace"] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            header('X-PSFS-DEBUG-TS: ' . Dispatcher::getInstance()->getTs() . ' s');
            header('X-PSFS-DEBUG-MEM: ' . Dispatcher::getInstance()->getMem('MBytes') . ' MBytes');
            header('X-PSFS-DEBUG-FILES: ' . count(get_included_files()) . ' files opened');
        }

        if(null !== $this->status_code)
        {
            header($this->status_code);
        }

        if($this->public_zone)
        {
            unset($_SERVER["PHP_AUTH_USER"]);
            unset($_SERVER["PHP_AUTH_PW"]);
            header_remove("Authorization");
        }else{
            header('Authorization:');
        }

        if(!empty($cookies)) foreach($cookies as $cookie)
        {
            setcookie($cookie["name"],
                $cookie["value"],
                (isset($cookie["expire"])) ? $cookie["expire"] : null,
                (isset($cookie["path"])) ? $cookie["path"] : "/",
                (isset($cookie["domain"])) ? $cookie["domain"] : Request::getInstance()->getRootUrl(false),
                (isset($cookie["secure"])) ? $cookie["secure"] : false,
                (isset($cookie["http"])) ? $cookie["http"] : false
            );
        }

        echo $this->dump($tpl, $vars);
        ob_flush();
        ob_end_clean();
        exit();
    }

    /**
     * Método que devuelve el contenido de una plantilla
     * @param $tpl
     * @param array $vars
     * @return string
     */
    public function dump($tpl, array $vars = array())
    {
        $vars["__user__"] = Security::getInstance()->getUser();
        $vars["__profiles__"] = Security::getCleanProfiles();
        return $this->tpl->render($tpl, $vars);
    }

    /**
     * Funcion Twig para los assets en las plantillas
     * @return $this
     */
    private function addAssetFunction()
    {
        $function = new \Twig_SimpleFunction('asset', function($string, $name = null, $return = true){
            $file_path = "";
            $debug = Config::getInstance()->get("debug");
            if(file_exists(BASE_DIR . $string))
            {
                $ppath = explode("/", $string);
                $original_filename = $ppath[count($ppath) -1];
                $base = BASE_DIR . DIRECTORY_SEPARATOR . "html" .DIRECTORY_SEPARATOR;
                if(preg_match('/\.css$/i', $string))
                {
                    $file = "/". substr(md5($string), 0, 8) . ".css";
                    $html_base = "css";
                    if($debug) $file = str_replace(".css", "_" . $original_filename, $file);
                }elseif(preg_match('/\.js$/i', $string))
                {
                    $file = "/". substr(md5($string), 0, 8) . ".js";
                    $html_base = "js";
                    if($debug) $file = str_replace(".js", "_" . $original_filename, $file);
                }elseif(preg_match("/image/i", mime_content_type(BASE_DIR . $string)))
                {
                    $ext = explode(".", $string);
                    $file = "/". substr(md5($string), 0, 8) . "." . $ext[count($ext) - 1];
                    $html_base = "image";
                    if($debug) $file = str_replace("." . $ext[count($ext) - 1], "_" . $original_filename, $file);
                }elseif(preg_match("/(doc|pdf)/i", mime_content_type(BASE_DIR . $string)))
                {
                    $ext = explode(".", $string);
                    $file = "/". substr(md5($string), 0, 8) . "." . $ext[count($ext) - 1];
                    $html_base = "docs";
                    if($debug) $file = str_replace("." . $ext[count($ext) - 1], "_" . $original_filename, $file);
                }elseif(preg_match("/(video|audio|ogg)/i", mime_content_type(BASE_DIR . $string)))
                {
                    $ext = explode(".", $string);
                    $file = "/". substr(md5($string), 0, 8) . "." . $ext[count($ext) - 1];
                    $html_base = "media";
                    if($debug) $file = str_replace("." . $ext[count($ext) - 1], "_" . $original_filename, $file);
                }elseif(!$return && !is_null($name))
                {
                    $html_base = '';
                    $file = $name;
                }
                $file_path = $html_base . $file;
                //Creamos el directorio si no existe
                if(!file_exists($base . $html_base)) @mkdir($base . $html_base, 0775);
                //Si se ha modificado
                if(!file_exists($base . $file_path) || filemtime($base . $file_path) < filemtime(BASE_DIR . $string))
                {
                    if($html_base == 'css')
                    {
                        $handle = @fopen(BASE_DIR . $string, 'r');
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
                                        $orig = realpath(dirname(BASE_DIR . $string) . DIRECTORY_SEPARATOR . $source_file);
                                        $orig_part = explode("Public", $orig);
                                        $dest = BASE_DIR . DIRECTORY_SEPARATOR . 'html' . $orig_part[1];
                                        if(!file_exists(dirname($dest))) @mkdir(dirname($dest), 0755, true);
                                        @copy($orig, $dest);
                                    }
                                }
                            }
                            fclose($handle);
                        }
                    }
                    $data = file_get_contents(BASE_DIR . $string);
                    if(!empty($name)) file_put_contents(BASE_DIR . DIRECTORY_SEPARATOR . "html" . DIRECTORY_SEPARATOR . $name, $data);
                    else file_put_contents($base . $file_path, $data);
                }
            }
            $return_path = (empty($name)) ? Request::getInstance()->getRootUrl() . '/' . $file_path : $name;
            return ($return) ? $return_path : '';
        });
        $this->tpl->addFunction($function);
        return $this;
    }

    /**
     * Función que pinta un formulario
     * @return $this
     */
    private function addFormsFunction()
    {
        $tpl = $this->tpl;
        $function = new \Twig_SimpleFunction('form', function(\PSFS\types\Form $form) use ($tpl) {
            return $tpl->display('forms/base.html.twig', array(
                'form' => $form,
            ));
        });
        $this->tpl->addFunction($function);
        return $this;
    }

    /**
     * Método que añade una nueva ruta al path de Twig
     * @param $path
     * @param $domain
     *
     * @return $this
     */
    public function addPath($path, $domain = '')
    {
        $loader = $this->tpl->getLoader();
        $loader->addPath($path, $domain);
        return $this;
    }

    /**
     * Función que pinta un campo de un formulario
     * @return $this
     */
    private function addFormWidgetFunction()
    {
        $tpl = $this->tpl;
        $function = new \Twig_SimpleFunction('form_widget', function(array $field, string $label = null) use ($tpl) {
            if(!empty($label)) $field["label"] = $label;
            //Limpiamos los campos obligatorios
            if(!isset($field["required"])) $field["required"] = true;
            elseif(isset($field["required"]) && (bool)$field["required"] === false) unset($field["required"]);
            return $tpl->display('forms/field.html.twig', array(
                'field' => $field,
            ));
        });
        $this->tpl->addFunction($function);
        return $this;
    }

    /**
     * Función que pinta un botón de un formulario
     * @return $this
     */
    private function addFormButtonFunction()
    {
        $tpl = $this->tpl;
        $function = new \Twig_SimpleFunction('form_button', function(array $button) use ($tpl) {
            return $tpl->display('forms/button.html.twig', array(
                'button' => $button,
            ));
        });
        $this->tpl->addFunction($function);
        return $this;
    }

    /**
     * Método que devuelve un parámetro de configuración en la plantilla
     * @return $this
     */
    private function addConfigFunction()
    {
        $tpl = $this->tpl;
        $function = new \Twig_SimpleFunction('get_config', function($param){
            return \PSFS\config\Config::getInstance()->get($param) ?: '';
        });
        $this->tpl->addFunction($function);
        return $this;
    }

    /**
     * Método que añade el filtro de traducción a Twig
     * @return $this
     */
    private function addTranslationFilter()
    {
        $filter = new \Twig_SimpleFilter('trans', function ($string) {
            return _($string);
        });
        $this->tpl->addFilter($filter);
        return $this;
    }

    /**
     * Método que añade la función path a Twig
     * @return $this
     */
    private function addRouteFunction()
    {
        $function = new \Twig_SimpleFunction('path', function($path = '', $absolute = false, $params = null){
            try{
                return Router::getInstance()->getRoute($path, $absolute, $params);
            }catch(\Exception $e)
            {
                return Router::getInstance()->getRoute('', $absolute);
            }
        });
        $this->tpl->addFunction($function);
        return $this;
    }

    /**
     * Método que copia directamente el recurso solicitado a la carpeta pública
     * @return $this
     */
    private function dumpResource()
    {
        $function = new \Twig_SimpleFunction('resource', function($path, $dest){
            $debug = Config::getInstance()->get("debug");
            if(file_exists(BASE_DIR . $path))
            {
                $destfolder = basename(BASE_DIR . $path);
                if(!file_exists(BASE_DIR . DIRECTORY_SEPARATOR . "html" . $dest . DIRECTORY_SEPARATOR . $destfolder) || $debug)
                {
                    try
                    {
                        @mkdir(BASE_DIR . DIRECTORY_SEPARATOR . "html" . $dest . DIRECTORY_SEPARATOR . $destfolder);
                    }catch (\Exception $e)
                    {
                        Logger::getInstance()->errorLog($e->getMessage() . "[" . $e->getCode() . "]");
                    }

                    self::copyr(BASE_DIR . $path, BASE_DIR . DIRECTORY_SEPARATOR . "html" . $dest);
                }
            }
            return '';
        });
        $this->tpl->addFunction($function);
        return $this;
    }

    static public function copyr($source, $dest)
    {
        // recursive function to copy
        // all subdirectories and contents:
        if(is_dir($source)) {
            $dir_handle=opendir($source);
            $sourcefolder = basename($source);
            $destfolder = basename($dest);
            if(!file_exists($dest."/".$sourcefolder)) @mkdir($dest."/".$sourcefolder);
            while($file=readdir($dir_handle)){
                if($file!="." && $file!=".."){
                    if(is_dir($source."/".$file)){
                        self::copyr($source."/".$file, $dest."/".$sourcefolder);
                    } else {
                        if(!file_exists($dest."/".$sourcefolder."/".$file)) @copy($source."/".$file, $dest."/".$sourcefolder."/".$file);
                    }
                }
            }
            @closedir($dir_handle);
        } else {
            // can also handle simple copy commands
            if(!file_exists($dest)) @copy($source, $dest);
        }
    }
}