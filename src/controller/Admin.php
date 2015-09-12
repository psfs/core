<?php

namespace PSFS\controller;

use PSFS\base\config\AdminForm;
use PSFS\base\config\Config;
use PSFS\base\config\ConfigForm;
use PSFS\base\config\LoginForm;
use PSFS\base\config\ModuleForm;
use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\Template;
use PSFS\base\types\AuthAdminController;
use PSFS\Services\GeneratorService;

/**
 * Class Admin
 * @package PSFS\controller
 * @domain ROOT
 */
class Admin extends AuthAdminController{

    const DOMAIN = 'ROOT';

    /**
     * @Inyectable
     * @var \PSFS\base\config\Config Servicio de configuración
     */
    protected $config;
    /**
     * @Inyectable
     * @var \PSFS\services\AdminServices Servicios de administración
     */
    protected $srv;
    /**
     * @Inyectable
     * @var  \PSFS\services\GeneratorService Servicio de generación de estructura de directorios
     */
    protected $gen;

    public function init() {
        parent::init();
        $this->setDomain('ROOT')
            ->setTemplatePath($this->config->getTemplatePath());
    }

    /**
     * Wrapper de asignación de los menus
     * @return array
     */
    protected function getMenu()
    {
        return Router::getInstance()->getAdminRoutes();
    }

    /**
     * Método que gestiona los usuarios administradores de la plataforma
     * @route /admin/setup
     * @return mixed
     * @throws \HttpException
     */
    public function adminers()
    {
        $admins = $this->srv->getAdmins();
        $form = new AdminForm();
        $form->build();
        if($this->getRequest()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                if(Security::save($form->getData()))
                {
                    Logger::getInstance()->infoLog("Configuración guardada correctamente");
                    return $this->getRequest()->redirect($this->getRoute("admin", true));
                }
                throw new \HttpException('Error al guardar los administradores, prueba a cambiar los permisos', 403);
            }
        }
        return $this->render('admin.html.twig', array(
            'admins' => $admins,
            'form' => $form,
            'profiles' => Security::getProfiles(),
        ));
    }

    /**
     * Acción que pinta un formulario genérico de login pra la zona restringida
     * @param string $route
     * @route /admin/login
     * @visible false
     * @return string HTML
     */
    public function adminLogin($route = null)
    {
        return Admin::staticAdminLogon($route);
    }

    /**
     * Método estático de login de administrador
     * @param string $route
     * @return string HTML
     * @throws \PSFS\base\exception\FormException
     */
    public static function staticAdminLogon($route = null) {
        $form = new LoginForm();
        if(Request::getInstance()->getMethod() == "GET") $form->setData(array("route" => $route));
        $form->build();
        $tpl = Template::getInstance();
        $tpl->setPublicZone(true);
        $template = "login.html.twig";
        $params = array(
            'form' => $form,
        );
        $cookies = array();
        if(Request::getInstance()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                if(Security::getInstance()->checkAdmin($form->getFieldValue("user"), $form->getFieldValue("pass")))
                {
                    $cookies = array(
                        array(
                            "name" => Security::getInstance()->getHash(),
                            "value" => base64_encode($form->getFieldValue("user") . ":" . $form->getFieldValue("pass")),
                            "expire" => time() + 3600,
                            "http" => true,
                        )
                    );
                    $template = "redirect.html.twig";
                    $params = array(
                        'route' => Router::getInstance()->getRoute("admin"),
                        'status_message' => _("Acceso permitido... redirigiendo!!"),
                        'delay' => 1,
                    );
                }else{
                    $form->setError("user", "El usuario no tiene acceso a la web");
                }
            }
        }
        return $tpl->render($template, $params, $cookies);
    }

    /**
     * Método que recorre los directorios para extraer las traducciones posibles
     * @param $locale string
     * @route /admin/translations/{locale}
     */
    public function getTranslations($locale = '')
    {
        //Idioma por defecto
        if(empty($locale)) $locale = $this->config->get("default_language");

        //Generamos las traducciones de las plantillas
        $translations = $this->tpl->regenerateTemplates();

        $locale_path = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $locale_path .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        //Localizamos xgettext
        $translations = array_merge($translations, GeneratorService::findTranslations(SOURCE_DIR, $locale));
        $translations = array_merge($translations, GeneratorService::findTranslations(CORE_DIR, $locale));
        $translations = array_merge($translations, GeneratorService::findTranslations(CACHE_DIR, $locale));

        $translations[] = "msgfmt {$locale_path}translations.po -o {$locale_path}translations.mo";
        $translations[] = shell_exec("export PATH=\$PATH:/opt/local/bin:/bin:/sbin; msgfmt {$locale_path}translations.po -o {$locale_path}translations.mo");
        return $this->render("translations.html.twig", array(
            "translations" => $translations,
        ));
    }

    /**
     * Método que gestiona la configuración de las variables
     * @Route /admin/config
     * @return mixed
     * @throws \HttpException
     */
    public function config() {
        Logger::getInstance()->infoLog("Arranque del Config Loader al solicitar ".$this->getRequest()->getRequestUri());
        /* @var $form \PSFS\base\config\ConfigForm */
        $form = new ConfigForm();
        $form->build();
        if($this->getRequest()->getMethod() == 'POST') {
            $form->hydrate();
            if($form->isValid()) {
                $debug = Config::getInstance()->getDebugMode();
                $newDebug = $form->getFieldValue("debug");
                if(Config::save($form->getData(), $form->getExtraData())) {
                    Logger::getInstance()->infoLog(_('Configuración guardada correctamente'));
                    //Verificamos si tenemos que limpiar la cache del DocumentRoot
                    if(boolval($debug) !== boolval($newDebug)) {
                        Config::clearDocumentRoot();
                    }
                    return $this->getRequest()->redirect($this->getRoute('admin'));
                }
                throw new \HttpException(_('Error al guardar la configuración, prueba a cambiar los permisos'), 403);
            }
        }
        return $this->render('welcome.html.twig', array(
            'text' => _("Bienvenido a PSFS"),
            'config' => $form,
            'typeahead_data' => array_merge(Config::$required, Config::$optional),
        ));
    }

    /**
     * Método que gestiona el menú de administración
     * @route /admin
     * @visible false
     * @return mixed
     */
    public function index()
    {
        return $this->render("index.html.twig", array(
            "routes" => Router::getInstance()->getAdminRoutes(),
        ));
    }

    /**
     * Método que genera un nuevo módulo
     * @route /admin/module
     *
     * @return string HTML
     * @throws \HttpException
     */
    public function generateModule()
    {
        Logger::getInstance()->infoLog("Arranque generador de módulos al solicitar ".$this->getRequest()->getRequestUri());
        /* @var $form \PSFS\base\config\ConfigForm */
        $form = new ModuleForm();
        $form->build();
        if($this->getRequest()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                $module = $form->getFieldValue("module");
                $force = $form->getFieldValue("force");
                $type = $form->getFieldValue("controllerType");
                try
                {
                    $this->gen->createStructureModule($module, $force, $type);
                    return $this->getRequest()->redirect(Router::getInstance()->getRoute("admin-module", true));
                }catch(\Exception $e)
                {
                    Logger::getInstance()->infoLog($e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
                    throw new ConfigException('Error al generar el módulo, prueba a cambiar los permisos', 403);
                }
            }
        }
        return $this->render("modules.html.twig", array(
            'properties' => $this->config->getPropelParams(),
            'form' => $form,
        ));
    }



    /**
     * Servicio que devuelve los parámetros disponibles
     * @route /admin/config/params
     * @visible false
     * @return mixed
     */
    public function getConfigParams()
    {
        $response = array_merge(Config::$required, Config::$optional);
        return $this->json($response);
    }

    /**
     * Método que pinta por pantalla todas las rutas del sistema
     * @route /admin/routes
     */
    public function printRoutes()
    {
        return $this->render('routing.html.twig', array(
            'slugs' => Router::getInstance()->getSlugs(),
        ));
    }

    /**
     * Servicio que devuelve los parámetros disponibles
     * @route /admin/routes/show
     * @visible false
     * @return mixed
     */
    public function getRouting()
    {
        $response = json_encode(array_keys(Router::getInstance()->getSlugs()));
        return $this->json($response);
    }

    /**
     * Servicio que muestra los logs del sistema
     * @route /admin/logs
     * @return mixed
     */
    public function logs()
    {
        $log = _("Selecciona un fichero de log");
        $logs = $this->srv->getLogFiles();

        $selected = '';
        $monthOpen = null;
        if($this->getRequest()->getMethod() == 'POST')
        {
            $selected = $this->getRequest()->get("log");
            list($log, $monthOpen) = $this->srv->formatLogFile($selected);
        }
        asort($logs);
        return $this->render("logs.html.twig", array(
            "logs" => $logs,
            "log" => $log,
            "selected" => $selected,
            "month_open" => $monthOpen,
        ));
    }

}
