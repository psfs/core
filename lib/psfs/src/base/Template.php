<?php

namespace PSFS\Base;

use PSFS\base\Singleton;
use PSFS\config\Config;
use PSFS\Dispatcher;
use PSFS\base\Router;

class Template extends Singleton{

    protected $tpl;
    protected $filters = array();

    protected $debug = false;

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
            ->addRouteFunction();
    }

    /**
     * Método que procesa la plantilla
     * @param $tpl
     * @param array $vars
     * @return mixed
     */
    public function render($tpl, array $vars = array())
    {
        ob_start();
        if($this->debug)
        {
            $vars["__DEBUG__"]["includes"] = get_included_files();
            header('X-PSFS-DEBUG-TS: ' . Dispatcher::getInstance()->getTs() . ' s');
            header('X-PSFS-DEBUG-MEM: ' . Dispatcher::getInstance()->getMem('MBytes') . ' MBytes');
            header('X-PSFS-DEBUG-FILES: ' . count(get_included_files()) . ' files opened');
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
        return $this->tpl->render($tpl, $vars);
    }

    /**
     * Funcion Twig para los assets en las plantillas
     * @return $this
     */
    private function addAssetFunction()
    {
        $function = new \Twig_SimpleFunction('asset', function($string){
            $file_path = "";
            if(file_exists(BASE_DIR . $string))
            {
                $base = BASE_DIR . "/html/";
                if(preg_match("/\.css$/i", $string))
                {
                    $file = "/". sha1($string) . ".css";
                    $html_base = "css";
                }elseif(preg_match("/\.js$/i", $string))
                {
                    $file = "/". sha1($string) . ".js";
                    $html_base = "js";
                }elseif(preg_match("/image/i", mime_content_type(BASE_DIR . $string)))
                {
                    $ext = explode(".", $string);
                    $file = "/". sha1($string) . "." . $ext[count($ext) - 1];
                    $html_base = "image";
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
                                        if(preg_match("/\#/", $source_file))
                                        {
                                            $source_file = explode("#", $source_file);
                                            $source_file = $source_file[0];
                                        }
                                        if(preg_match("/\?/", $source_file))
                                        {
                                            $source_file = explode("?", $source_file);
                                            $source_file = $source_file[0];
                                        }
                                        $orig = realpath(dirname(BASE_DIR . $string) . DIRECTORY_SEPARATOR . $source_file);
                                        $dest = BASE_DIR . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR . 'css' .DIRECTORY_SEPARATOR . $source_file;
                                        if(!file_exists(dirname($dest))) @mkdir(dirname($dest));
                                        @copy($orig, $dest);
                                    }
                                }
                            }
                            fclose($handle);
                        }
                    }
                    $data = file_get_contents(BASE_DIR . $string);
                    file_put_contents($base . $file_path, $data);
                }
            }
            return '/' . $file_path;
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
        $function = new \Twig_SimpleFunction('path', function($path = ''){
            return Router::getInstance()->getRoute($path);
        });
        $this->tpl->addFunction($function);
        return $this;
    }
}