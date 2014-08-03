<?php

namespace PSFS\controller;

use PSFS\base\config\Config;
use PSFS\base\config\AdminForm;
use PSFS\base\config\ConfigForm;
use PSFS\base\config\LoginForm;
use PSFS\base\config\ModuleForm;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\social\form\GenerateShortUrlForm;
use PSFS\base\social\form\GoogleUrlShortenerForm;
use PSFS\base\social\GoogleUrlShortener;
use PSFS\base\types\AuthController;
use Symfony\Component\Finder\Finder;

/**
 * Class Admin
 * @package PSFS\controller
 */
class Admin extends AuthController{

    private $config;
    /**
     * Constructor por defecto
     */
    public function __construct()
    {
        parent::__construct();
        $this->config = Config::getInstance();
        $this->setDomain('')
            ->setTemplatePath(Config::getInstance()->getTemplatePath());
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
     * Redefinimos el método de getDomain
     * @return string
     */
    public function getDomain(){ return ''; }

    /**
     * Método que gestiona los usuarios administradores de la plataforma
     * @route /admin/setup
     * @return mixed
     */
    public function adminers()
    {
        $admins = $this->security->getAdmins();
        if(!empty($admins))
        {
            if(!$this->security->checkAdmin())
            {
                if("login" === $this->config->get("admin_login")) return $this->adminLogin("admin-setup");
                header('HTTP/1.1 401 Unauthorized');
                header('WWW-Authenticate: Basic Realm="PSFS"');
                echo _("Es necesario ser administrador para ver ésta zona");
                exit();
            }
        }
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
                    return $this->getRequest()->redirect();
                }
                throw new \HttpException('Error al guardar los administradores, prueba a cambiar los permisos', 403);
            }
        }
        if(!empty($admins)) foreach($admins as &$admin)
        {
            if(isset($admin["profile"]))
            {
                $admin["class"] = $admin["profile"] == sha1("admin") ? 'primary' : "warning";
            }else{
                $admin["class"] = "primary";
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
     * @params string $route
     * @route /admin/login
     * @return html
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
                        'delay' => 3,
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
        $translations = self::findTranslations(SOURCE_DIR, $locale);
        $translations = self::findTranslations(CORE_DIR, $locale);
        $translations = self::findTranslations(CACHE_DIR, $locale);
        echo "<hr>";
        echo _('Compilando traducciones');
        pre("msgfmt {$locale_path}translations.po -o {$locale_path}translations.mo");
        exec("export PATH=\$PATH:/opt/local/bin:/bin:/sbin; msgfmt {$locale_path}translations.po -o {$locale_path}translations.mo", $result);
        echo "Fin";
        pre($result);
        exit();
    }

    /**
     * Método que revisa las traducciones directorio a directorio
     * @param $path
     * @param $locale
     */
    private static function findTranslations($path, $locale)
    {
        $locale_path = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $locale_path .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        $translations = false;
        $d = dir($path);
        while(false !== ($dir = $d->read()))
        {
            if(!file_exists($locale_path)) mkdir($locale_path, 0777, true);
            if(!file_exists($locale_path . 'translations.po')) file_put_contents($locale_path . 'translations.po', '');
            $inspect_path = realpath($path.DIRECTORY_SEPARATOR.$dir);
            $cmd_php = "export PATH=\$PATH:/opt/local/bin; xgettext ". $inspect_path . DIRECTORY_SEPARATOR ."*.php --from-code=UTF-8 -j -L PHP --debug --force-po -o {$locale_path}translations.po";
            if(is_dir($path.DIRECTORY_SEPARATOR.$dir) && preg_match('/^\./',$dir) == 0)
            {
                $return = array();
                echo "<li>" . _('Revisando directorio: ') . $inspect_path;
                echo "<li>" . _('Comando ejecutado: '). $cmd_php;
                shell_exec($cmd_php);// . " >> " . __DIR__ . DIRECTORY_SEPARATOR . "debug.log 2>&1");
                usleep(10);
                $translations = self::findTranslations($inspect_path, $locale);
            }
        }
        return $translations;
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
        ));
    }

    /**
     * Método que gestiona el menú de administración
     * @route /admin
     * @route /admin/
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
     * @return mixed
     */
    public function generateModule($module = '')
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
                    $this->createStructureModule($module, Logger::getInstance());
                    return $this->getRequest()->redirect(Router::getInstance()->getRoute("admin", true));
                }catch(\Exception $e)
                {
                    Logger::getInstance()->infoLog($e->getMessage() . "[" . $e->getLine() . "]");
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
     * Servicio que genera la estructura de un módulo o lo actualiza en caso de ser necesario
     * @param $module
     * @param $logger
     * @param $pb
     *
     * @return mixed
     */
    public function createStructureModule($module, $logger, $pb = null)
    {
        $mod_path = BASE_DIR . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR;
        $module = ucfirst($module);
        //Creamos el directorio base de los módulos
        if(!file_exists($mod_path)) mkdir($mod_path, 0775);
        //Creamos la carpeta del módulo
        if(!file_exists($mod_path . $module)) mkdir($mod_path . $module, 0755);
        //Creamos las carpetas CORE del módulo
        $logger->infoLog("Generamos la estructura");
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Config")) mkdir($mod_path . $module . DIRECTORY_SEPARATOR . "Config", 0755);
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Controller")) mkdir($mod_path . $module . DIRECTORY_SEPARATOR . "Controller", 0755);
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Form")) mkdir($mod_path . $module . DIRECTORY_SEPARATOR . "Form", 0755);
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Models")) mkdir($mod_path . $module . DIRECTORY_SEPARATOR . "Models", 0755);
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Public")) mkdir($mod_path . $module . DIRECTORY_SEPARATOR . "Public", 0755);
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Templates")) mkdir($mod_path . $module . DIRECTORY_SEPARATOR . "Templates", 0755);
        //Creamos las carpetas de los assets
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "css")) mkdir($mod_path . $module . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "css", 0755);
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "js")) mkdir($mod_path . $module . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "js", 0755);
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "img")) mkdir($mod_path . $module . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "img", 0755);
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "font")) mkdir($mod_path . $module . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . "font", 0755);
        //Generamos el controlador base
        $logger->infoLog("Generamos el controlador BASE");
        $controller = $this->tpl->dump("generator/controller.template.twig", array(
            "module" => $module,
        ));
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Controller" . DIRECTORY_SEPARATOR . "{$module}.php")) file_put_contents($mod_path . $module . DIRECTORY_SEPARATOR . "Controller" . DIRECTORY_SEPARATOR . "{$module}.php", $controller);
        //Generamos el autoloader del módulo
        $logger->infoLog("Generamos el autoloader");
        $autoloader = $this->tpl->dump("generator/autoloader.template.twig", array(
            "module" => $module,
        ));
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "autoload.php")) file_put_contents($mod_path . $module . DIRECTORY_SEPARATOR . "autoload.php", $autoloader);
        //Generamos el autoloader del módulo
        $logger->infoLog("Generamos el schema");
        $schema = $this->tpl->dump("generator/schema.propel.twig", array(
            "module" => $module,
            "db" => $this->config->get("db_name"),
        ));
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "schema.xml")) file_put_contents($mod_path . $module . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "schema.xml", $schema);
        $logger->infoLog("Generamos la configuración de Propel");
        $build_properties = $this->tpl->dump("generator/build.properties.twig", array(
            "module" => $module,
            "host" => $this->config->get("db_host"),
            "port" => $this->config->get("db_port"),
            "user" => $this->config->get("db_user"),
            "pass" => $this->config->get("db_password"),
            "db" => $this->config->get("db_name"),
        ));
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "propel.yml")) file_put_contents($mod_path . $module . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "propel.yml", $build_properties);
        //Generamos la plantilla de index
        $index = $this->tpl->dump("generator/index.template.twig");
        $logger->infoLog("Generamos una plantilla base por defecto");
        if(!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Templates" . DIRECTORY_SEPARATOR . "index.html.twig")) file_put_contents($mod_path . $module . DIRECTORY_SEPARATOR . "Templates" . DIRECTORY_SEPARATOR . "index.html.twig", $index);
        //Generamos las clases de propel y la configuración
        $exec = "export PATH=\$PATH:/opt/local/bin; " . BASE_DIR . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "propel ";
        $opt = " --input-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config --output-dir=" . CORE_DIR . " --verbose";
        $ret = shell_exec($exec . "build" . $opt);
        $logger->infoLog("Generamos clases invocando a propel:\n $ret");
        $ret = shell_exec($exec . "sql:build" . $opt . " --output-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config");
        $logger->infoLog("Generamos sql invocando a propel:\n $ret");
        $ret = shell_exec($exec . "config:convert" . $opt . " --output-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config");
        $logger->infoLog("Generamos configuración invocando a propel:\n $ret");
        //Redireccionamos al home definido
        $logger->infoLog("Módulo generado correctamente");
    }

    /**
     * Servicio que devuelve los parámetros disponibles
     * @route /admin/config/params
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
     * @translation _("Servicio json urls")
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
        $logs = array();
        $files = new Finder();
        $files->files()->in(LOG_DIR)->name("*.log")->sortByModifiedTime();
        $selected = '';
        if($this->getRequest()->getMethod() == 'POST')
        {
            $selected = $this->getRequest()->get("log");
        }
        foreach($files as $file)
        {
            $size = $file->getSize() / 8 / 1024;
            $logs[] =array(
                "filename" => $file->getFilename(),
                "size" => round($size, 3)
            );
            if($file->getFilename() == $selected) $log = $file->getContents();
        }
        return $this->render("logs.html.twig", array(
            "logs" => $logs,
            "log" => $log,
            "selected" => $selected,
        ));
    }

    /**
     * Servicio que configura la api key de Google Url Shortener
     * @route /admin/social/gus
     */
    public function configApiKey()
    {
        Logger::getInstance()->infoLog("Arranque del Config Loader al solicitar ".$this->getRequest()->getrequestUri());
        /* @var $form \PSFS\social\form\GoogleUrlShortenerForm */
        $gs = new GoogleUrlShortener();
        $form = new GoogleUrlShortenerForm();
        $form->build();
        $form->setData(array(
            "api_key" => $gs->getApyKey(),
        ));
        if($this->getRequest()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                if($gs->save($form->getData()))
                {
                    Logger::getInstance()->infoLog("Configuración guardada correctamente");
                    return $this->getRequest()->redirect();
                }
                throw new \HttpException('Error al guardar la configuración, prueba a cambiar los permisos', 403);
            }
        }
        return $this->render('welcome.html.twig', array(
            'text' => _("Bienvenido a PSFS"),
            'config' => $form,
        ));
    }

    /**
     * Servicio que genera la url acortada de una dirección
     * @route /admin/social/gus/generate
     * @return mixed
     * @throws \HttpException
     */
    public function genShortUrl()
    {
        Logger::getInstance()->infoLog("Arranque del Config Loader al solicitar ".$this->getRequest()->getrequestUri());
        /* @var $form \PSFS\social\form\GenerateShortUrlForm */
        $form = new GenerateShortUrlForm();
        $gs = new GoogleUrlShortener();
        $form->build();
        if($this->getRequest()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                $data = $form->getData();
                pre($gs->shortUrl($data["url"]), true);
            }
        }
        return $this->render('welcome.html.twig', array(
            'text' => _("Bienvenido a PSFS"),
            'config' => $form,
        ));
    }

}