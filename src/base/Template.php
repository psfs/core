<?php

namespace PSFS\base;


use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\extension\AssetsTokenParser;
use PSFS\base\types\Form;
use PSFS\Dispatcher;


class Template extends Singleton{

    protected $tpl;
    protected $filters = array();

    protected $debug = false;
    protected $public_zone = true;
    private $status_code = null;

    /**
     *
     */
    function __construct()
    {
        $this->debug = Config::getInstance()->getDebugMode() ?: false;
        $loader = new \Twig_Loader_Filesystem(Config::getInstance()->getTemplatePath());
        $this->tpl = new \Twig_Environment($loader, array(
            'cache' => Config::getInstance()->getCachePath(),
            'debug' => (bool)$this->debug,
            'auto_reload' => true,
        ));
        //Asignamos las funciones especiales
        $this->addAssetFunction()
            ->addFormsFunction()
            ->addFormWidgetFunction()
            ->addFormButtonFunction()
            ->addConfigFunction()
            ->addRouteFunction()
            ->dumpResource();

        //Añadimos las extensiones de los tags
        $this->tpl->addTokenParser(new AssetsTokenParser("css"));
        $this->tpl->addTokenParser(new AssetsTokenParser("js"));

        //Optimizamos
        $this->tpl->addExtension(new \Twig_Extension_Optimizer());
        $this->tpl->addExtension(new \Twig_Extensions_Extension_I18n());
    }

    /**
     * Método que devuelve el loader del Template
     * @return \Twig_LoaderInterface
     */
    public function getLoader(){ return $this->tpl->getLoader(); }

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
     *
     * @param $tpl
     * @param array $vars
     * @param array $cookies
     *
     * @return mixed
     */
    public function render($tpl, array $vars = array(), $cookies = array())
    {
        ob_clean();
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

        if(!empty($cookies) && is_array($cookies)) foreach($cookies as $cookie)
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
        exit;
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
            $debug = Config::getInstance()->getDebugMode();
            $filename_path = $string;
            if(!file_exists($file_path)) $file_path = BASE_DIR . $string;
            $domains = self::getDomains(true);
            if(!file_exists($file_path) && !empty($domains)) foreach($domains as $domain => $paths)
            {
                $domain_filename = str_replace($domain, $paths["public"], $string);
                if(file_exists($domain_filename))
                {
                    $filename_path = $domain_filename;
                    continue;
                }
            }
            if(file_exists($filename_path))
            {
                $ppath = explode("/", $string);
                $original_filename = $ppath[count($ppath) -1];
                $base = WEB_DIR .DIRECTORY_SEPARATOR;
                $file = "";
                $html_base = "";
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
                }elseif(preg_match("/image/i", mime_content_type($filename_path)))
                {
                    $ext = explode(".", $string);
                    $file = "/". substr(md5($string), 0, 8) . "." . $ext[count($ext) - 1];
                    $html_base = "img";
                    if($debug) $file = str_replace("." . $ext[count($ext) - 1], "_" . $original_filename, $file);
                }elseif(preg_match("/(doc|pdf)/i", mime_content_type($filename_path)))
                {
                    $ext = explode(".", $string);
                    $file = "/". substr(md5($string), 0, 8) . "." . $ext[count($ext) - 1];
                    $html_base = "docs";
                    if($debug) $file = str_replace("." . $ext[count($ext) - 1], "_" . $original_filename, $file);
                }elseif(preg_match("/(video|audio|ogg)/i", mime_content_type($filename_path)))
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
                if(!file_exists($base . $html_base)) {
                    if(@mkdir($base . $html_base, 0775, true) === false) {
                        throw new ConfigException("Can't create directory " . $base . $html_base);
                    }
                }
                //Si se ha modificado
                if(!file_exists($base . $file_path) || filemtime($base . $file_path) < filemtime($filename_path))
                {
                    if($html_base == 'css')
                    {
                        $handle = @fopen($filename_path, 'r');
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
                                        $orig = realpath(dirname($filename_path) . DIRECTORY_SEPARATOR . $source_file);
                                        $orig_part = explode("Public", $orig);
                                        $dest = WEB_DIR . $orig_part[1];
                                        if(!file_exists(dirname($dest))) {
                                            if(@mkdir(dirname($dest), 0755, true) === false) {
                                                throw new ConfigException("Can't create directory " . $dest);
                                            }
                                        }
                                        @copy($orig, $dest);
                                    }
                                }
                            }
                            fclose($handle);
                        }
                    }
                    $data = file_get_contents($filename_path);
                    if(!empty($name)) file_put_contents(WEB_DIR . DIRECTORY_SEPARATOR . $name, $data);
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
        $function = new \Twig_SimpleFunction('form', function(Form $form) use ($tpl) {
            $tpl->display('forms/base.html.twig', array(
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
        $this->tpl->getLoader()->addPath($path, $domain);
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
            $tpl->display('forms/field.html.twig', array(
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
            $tpl->display('forms/button.html.twig', array(
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
            return Config::getInstance()->get($param) ?: '';
        });
        $tpl->addFunction($function);
        return $this;
    }

    /*
     * Servicio que regenera todas las plantillas
     */
    public function regenerateTemplates()
    {
        //Generamos los dominios por defecto del fmwk
        foreach($this->tpl->getLoader()->getPaths() as $path) $this->generateTemplate($path);
        $domains = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . "domains.json"), true);
        if(!empty($domains)) foreach($domains as $domain => $paths)
        {
            $this->addPath($paths["template"], $domain);
            $this->generateTemplate($paths["template"], $domain);
        }
        pre(_("Plantillas regeneradas correctamente"));
    }

    protected function generateTemplate($tplDir, $domain = '')
    {
        pre(str_replace("%d", $domain, str_replace("%s", $tplDir, _("Generando plantillas en path '%s' para el dominio '%d'"))));
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tplDir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file)
        {
            // force compilation
            if ($file->isFile()) {
                try{
                    $this->tpl->loadTemplate(str_replace($tplDir.'/', '', $file));
                }catch(\Exception $e)
                {
                    Logger::getInstance()->errorLog($e->getMessage());
                }
            }
        }
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
        $function = new \Twig_SimpleFunction('resource', function($path, $dest, $force = false){
            $debug = Config::getInstance()->getDebugMode();
            $domains = self::getDomains(true);
            $filename_path = $path;
            if(!file_exists($path) && !empty($domains)) foreach($domains as $domain => $paths)
            {
                $domain_filename = str_replace($domain, $paths["public"], $path);
                if(file_exists($domain_filename))
                {
                    $filename_path = $domain_filename;
                    continue;
                }
            }
            if(file_exists($filename_path))
            {
                $destfolder = basename($filename_path);
                if(!file_exists(WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder) || $debug || $force)
                {
                    if(@mkdir(WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder, 0775, true) === false) {
                        throw new ConfigException("Can't create directory " . WEB_DIR . $dest . DIRECTORY_SEPARATOR . $destfolder);
                    }

                    self::copyr($filename_path, WEB_DIR . $dest);
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
            if(!file_exists($dest."/".$sourcefolder)) {
                if(@mkdir($dest."/".$sourcefolder, 0755, true) === false) {
                    throw new ConfigException("Can't create directory " . $dest . "/" . $sourcefolder);
                }
            }
            while($file=readdir($dir_handle)){
                if($file!="." && $file!=".."){
                    if(is_dir($source."/".$file)){
                        self::copyr($source."/".$file, $dest."/".$sourcefolder);
                    } else {
                        if(!file_exists($dest."/".$sourcefolder."/".$file) || filemtime($dest."/".$sourcefolder."/".$file) != filemtime($source."/".$file)) @copy($source."/".$file, $dest."/".$sourcefolder."/".$file);
                    }
                }
            }
            @closedir($dir_handle);
        } else {
            // can also handle simple copy commands
            if(!file_exists($dest)) @copy($source, $dest);
        }
    }

    /**
     * Método que devuelve los dominios de una plataforma
     * @param bool $append
     * @return mixed
*/
    static public function getDomains($append = false)
    {
        $domains = Router::getInstance()->getDomains();
        if($append) foreach($domains as &$domain)
        {
            foreach($domain as &$path) $path .= DIRECTORY_SEPARATOR;
        }
        return $domains;
    }
}