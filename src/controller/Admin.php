<?php

namespace PSFS\controller;

use PSFS\base\config\AdminForm;
use PSFS\base\config\Config;
use PSFS\base\config\ConfigForm;
use PSFS\base\config\LoginForm;
use PSFS\base\config\ModuleForm;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\types\AuthController;
use PSFS\Services\GeneratorService;

/**
 * Class Admin
 * @package PSFS\controller
 * @domain ROOT
 */
class Admin extends AuthController{

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
     * @params $route string
     * @route /admin/login
     * @visible false
     * @return string HTML
     */
    public function adminLogin($route = null)
    {
        $form = new LoginForm();
        if($this->getRequest()->getMethod() == "GET") $form->setData(array("route" => $route));
        $form->build();
        if($this->getRequest()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                if($this->security->checkAdmin($form->getFieldValue("user"), $form->getFieldValue("pass")))
                {
                    $cookies = array(
                        array(
                            "name" => $this->security->getHash(),
                            "value" => base64_encode($form->getFieldValue("user") . ":" . $form->getFieldValue("pass")),
                            "expire" => time() + 3600,
                            "http" => true,
                        )
                    );
                    return $this->render("redirect.html.twig", array(
                        'route' => $form->getFieldValue("route"),
                        'status_message' => _("Acceso permitido... redirigiendo!!"),
                        'delay' => 1,
                    ), $cookies);
                }else{
                    $form->setError("user", "El usuario no tiene acceso a la web");
                }
            }
        }
        return $this->render("login.html.twig", array(
            'form' => $form,
        ));
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
        $this->tpl->regenerateTemplates();

        $locale_path = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $locale_path .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        //Localizamos xgettext
        $translations = GeneratorService::findTranslations(SOURCE_DIR, $locale);
        $translations = GeneratorService::findTranslations(CORE_DIR, $locale);
        $translations = GeneratorService::findTranslations(CACHE_DIR, $locale);

        echo "<hr>";
        echo _('Compilando traducciones');
        pre("msgfmt {$locale_path}translations.po -o {$locale_path}translations.mo");
        exec("export PATH=\$PATH:/opt/local/bin:/bin:/sbin; msgfmt {$locale_path}translations.po -o {$locale_path}translations.mo", $result);
        echo "Fin";
        exit;
    }



    /**
     * Método que gestiona la configuración de las variables
     * @Route /admin/config
     * @return mixed
     * @throws \HttpException
     */
    public function config(){
        Logger::getInstance()->infoLog("Arranque del Config Loader al solicitar ".$this->getRequest()->getrequestUri());
        /* @var $form \PSFS\base\config\ConfigForm */
        $form = new ConfigForm();
        $form->build();
        if($this->getRequest()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                if(Config::save($form->getData(), $form->getExtraData()))
                {
                    Logger::getInstance()->infoLog("Configuración guardada correctamente");
                    return $this->getRequest()->redirect($this->getRoute('admin'));
                }
                throw new \HttpException('Error al guardar la configuración, prueba a cambiar los permisos', 403);
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
        Logger::getInstance()->infoLog("Arranque generador de módulos al solicitar ".$this->getRequest()->getrequestUri());
        /* @var $form \PSFS\base\config\ConfigForm */
        $form = new ModuleForm();
        $form->build();
        if($this->getRequest()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                $module = $form->getFieldValue("module");
                try
                {
                    $this->gen->createStructureModule($module, Logger::getInstance());
                    return $this->getRequest()->redirect(Router::getInstance()->getRoute("admin-module", true));
                }catch(\Exception $e)
                {
                    Logger::getInstance()->infoLog($e->getMessage() . " [" . $e->getFile() . ":" . $e->getLine() . "]");
                    throw new \HttpException('Error al generar el módulo, prueba a cambiar los permisos', 403);
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
        $response = json_encode(array_merge(Config::$required, Config::$optional));
        ob_start();
        header("Content-type: text/json");
        header("Content-length: " . count($response));
        echo $response;
        ob_flush();
        ob_end_clean();
        exit();
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
        ob_start();
        header("Content-type: text/json");
        header("Content-length: " . count($response));
        echo $response;
        ob_flush();
        ob_end_clean();
        exit();
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
