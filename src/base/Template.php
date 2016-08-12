<?php

namespace PSFS\base;


use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\extension\AssetsTokenParser;
use PSFS\base\extension\TemplateFunctions;
use PSFS\base\types\SingletonTrait;
use PSFS\Dispatcher;


class Template {

    use SingletonTrait;
    /**
     * @var \Twig_Environment tpl
     */
    protected $tpl;
    protected $filters = array();

    protected $debug = false;
    protected $public_zone = true;
    private $status_code = 200;

    /**
     * @var \PSFS\base\Security $security
     */
    protected $security;

    /**
     * @var \PSFS\base\Cache $cache
     */
    protected $cache;

    /**
     * Constructor por defecto
     */
    public function __construct() {
        $this->setup();
        $this->addTemplateFunctions();
        $this->addTemplateTokens();
        $this->optimizeTemplates();
    }

    /**
     * Método que devuelve el loader del Template
     * @return \Twig_LoaderInterface
     */
    public function getLoader() {
        return $this->tpl->getLoader();
    }

    /**
     * Método que activa la zona pública
     * @param bool $public
     *
     * @return Template
     */
    public function setPublicZone($public = true) {
        $this->public_zone = $public;
        return $this;
    }

    /**
     * Método que establece un header de http status code
     * @param string $status
     *
     * @return Template
     */
    public function setStatus($status = null) {
        switch ($status)
        {
            //TODO implement all status codes
            case '500': $this->status_code = "HTTP/1.0 500 Internal Server Error"; break;
            case '404': $this->status_code = "HTTP/1.0 404 Not Found"; break;
            case '403': $this->status_code = "HTTP/1.0 403 Forbidden"; break;
            case '402': $this->status_code = "HTTP/1.0 402 Payment Required"; break;
            case '401': $this->status_code = "HTTP/1.0 401 Unauthorized"; break;
            case '400': $this->status_code = "HTTP/1.0 400 Bad Request"; break;
        }
        return $this;
    }

    /**
     * Método que procesa la plantilla
     *
     * @param string $tpl
     * @param array $vars
     * @param array $cookies
     *
     * @return string HTML
     */
    public function render($tpl, array $vars = array(), array $cookies = array()) {
        Logger::log('Start render response');
        $vars = $this->setDebugHeaders($vars);
        $output = $this->dump($tpl, $vars);

        return $this->output($output, 'text/html', $cookies);
    }

    /**
     * Servicio que establece las cabeceras de la respuesta
     * @param string $contentType
     * @param array $cookies
     */
    private function setReponseHeaders($contentType = 'text/html', array $cookies = array()) {
        $config = Config::getInstance();
        $powered = $config->get("poweredBy");
        if (empty($powered)) {
            $powered = "@c15k0";
        }
        header("X-Powered-By: $powered");
        $this->setStatusHeader();
        $this->setAuthHeaders();
        $this->setCookieHeaders($cookies);
        header('Content-type: '.$contentType);

    }

    /**
     * Servicio que devuelve el output
     * @param string $output
     * @param string $contentType
     * @param array $cookies
     * @return string HTML
     */
    public function output($output = '', $contentType = 'text/html', array $cookies = array()) {
        Logger::log('Start output response');
        ob_start();
        $this->setReponseHeaders($contentType, $cookies);
        header('Content-length: '.strlen($output));

        $cache = Cache::needCache();
        if (false !== $cache && $this->status_code === 200 && $this->debug === false) {
            Logger::log('Saving output response into cache');
            $cacheName = $this->cache->getRequestCacheHash();
            $this->cache->storeData("json".DIRECTORY_SEPARATOR.$cacheName, $output);
            $this->cache->storeData("json".DIRECTORY_SEPARATOR.$cacheName.".headers", headers_list(), Cache::JSON);
        }
        echo $output;

        ob_flush();
        ob_end_clean();
        Logger::log('End output response');
        $this->closeRender();
    }

    /**
     * Método que cierra y limpia los buffers de salida
     */
    public function closeRender() {
        Logger::log('Close template render');
        $this->security->setSessionKey("lastRequest", array(
            "url" => Request::getInstance()->getRootUrl().Request::requestUri(),
            "ts" => microtime(true),
        ));
        $this->security->updateSession();
        exit;
    }

    /**
     * Método que devuelve los datos cacheados con las cabeceras que tenía por entonces
     * @param string $data
     * @param string|null $headers
     */
    public function renderCache($data, $headers = array()) {
        ob_start();
        for ($i = 0, $ct = count($headers); $i < $ct; $i++) {
            header($headers[$i]);
        }
        echo $data;
        ob_flush();
        ob_end_clean();
        $this->closeRender();
    }

    /**
     * Método que añade una nueva ruta al path de Twig
     * @param $path
     * @param $domain
     *
     * @return Template
     */
    public function addPath($path, $domain = '') {
        $this->tpl->getLoader()->addPath($path, $domain);
        return $this;
    }

    /**
     * Método que devuelve el contenido de una plantilla
     * @param string $tpl
     * @param array $vars
     * @return string
     */
    public function dump($tpl, array $vars = array()) {
        $vars["__user__"] = $this->security->getUser();
        $vars["__admin__"] = $this->security->getAdmin();
        $vars["__profiles__"] = Security::getCleanProfiles();
        $vars["__flash__"] = Security::getInstance()->getFlashes();
        $dump = '';
        try {
            $dump = $this->tpl->render($tpl, $vars);
        }catch (\Exception $e) {
            echo $e->getMessage()."<pre>".$e->getTraceAsString()."</pre>";
        }
        return $dump;
    }

    /**
     * Método que añade una función al motor de plantillas
     * @param string $templateFunction
     * @param $functionName
     *
     * @return Template
     */
    protected function addTemplateFunction($templateFunction, $functionName) {
        $function = new \Twig_SimpleFunction($templateFunction, $functionName);
        $this->tpl->addFunction($function);
        return $this;
    }

    /**
     * Funcion Twig para los assets en las plantillas
     * @return Template
     */
    private function addAssetFunction() {
        return $this->addTemplateFunction("asset", TemplateFunctions::ASSETS_FUNCTION);
    }

    /**
     * Función que pinta un formulario
     * @return Template
     */
    private function addFormsFunction() {
        return $this->addTemplateFunction("form", TemplateFunctions::FORM_FUNCTION);
    }

    /**
     * Función que pinta un campo de un formulario
     * @return Template
     */
    private function addFormWidgetFunction()
    {
        return $this->addTemplateFunction("form_widget", TemplateFunctions::WIDGET_FUNCTION);
    }

    /**
     * Función que pinta un botón de un formulario
     * @return Template
     */
    private function addFormButtonFunction() {
        return $this->addTemplateFunction("form_button", TemplateFunctions::BUTTON_FUNCTION);
    }

    /**
     * Método que devuelve un parámetro de configuración en la plantilla
     * @return Template
     */
    private function addConfigFunction() {
        return $this->addTemplateFunction("get_config", TemplateFunctions::CONFIG_FUNCTION);
    }

    /**
     * Método que añade la función path a Twig
     * @return Template
     */
    private function addRouteFunction() {
        return $this->addTemplateFunction("path", TemplateFunctions::ROUTE_FUNCTION);
    }

    /**
     * Método que copia directamente el recurso solicitado a la carpeta pública
     * @return Template
     */
    private function addResourceFunction() {
        return $this->addTemplateFunction("resource", TemplateFunctions::RESOURCE_FUNCTION);
    }

    /**
     * @return Template
     */
    private function addSessionFunction() {
        return $this->addTemplateFunction("session", TemplateFunctions::SESSION_FUNCTION);
    }

    /**
     * @return Template
     */
    private function addExistsFlashFunction() {
        return $this->addTemplateFunction("existsFlash", TemplateFunctions::EXISTS_FLASH_FUNCTION);
    }

    /**
     * @return Template
     */
    private function addGetFlashFunction() {
        return $this->addTemplateFunction("getFlash", TemplateFunctions::GET_FLASH_FUNCTION);
    }

    /**
     * Servicio que regenera todas las plantillas
     * @return array
     */
    public function regenerateTemplates() {
        $this->generateTemplatesCache();
        $domains = Cache::getInstance()->getDataFromFile(CONFIG_DIR.DIRECTORY_SEPARATOR."domains.json", Cache::JSON, true);
        if (is_array($domains)) {
            $translations = $this->parsePathTranslations($domains);
        }
        $translations[] = _("Plantillas regeneradas correctamente");
        return $translations;
    }

    /**
     * @param $tplDir
     * @param string $domain
     *
     * @return mixed
     */
    protected function generateTemplate($tplDir, $domain = '') {
        $templatesDir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tplDir), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($templatesDir as $file) {
            // force compilation
            if ($file->isFile()) {
                try {
                    $this->tpl->loadTemplate(str_replace($tplDir.'/', '', $file));
                } catch (\Exception $e) {
                    Logger::log($e->getMessage(), LOG_ERR);
                }
            }
        }
        return str_replace("%d", $domain, str_replace("%s", $tplDir, _("Generando plantillas en path '%s' para el dominio '%d'")));
    }

    /**
     * Método que extrae el path de un string
     * @param $path
     *
     * @return string
     */
    public static function extractPath($path) {
        $explodePath = explode(DIRECTORY_SEPARATOR, $path);
        $realPath = array();
        for ($i = 0, $parts = count($explodePath) - 1; $i < $parts; $i++) {
            $realPath[] = $explodePath[$i];
        }
        return implode(DIRECTORY_SEPARATOR, $realPath);
    }

    /**
     * Método que devuelve los dominios de una plataforma
     * @param bool $append
     * @return array
     */
    static public function getDomains($append = false) {
        $domains = Router::getInstance()->getDomains();
        if ($append) {
            foreach ($domains as &$domain) {
            foreach ($domain as &$path) {
                $path .= DIRECTORY_SEPARATOR;
        }
            }
        }
        return $domains;
    }

    /**
     * @param $cookies
     */
    protected function setCookieHeaders($cookies) {
        if (!empty($cookies) && is_array($cookies)) {
            foreach ($cookies as $cookie) {
            setcookie($cookie["name"],
                $cookie["value"],
                (array_key_exists('expire', $cookie)) ? $cookie["expire"] : NULL,
                (array_key_exists('path', $cookie)) ? $cookie["path"] : "/",
                (array_key_exists('domain', $cookie)) ? $cookie["domain"] : Request::getInstance()->getRootUrl(FALSE),
                (array_key_exists('secure', $cookie)) ? $cookie["secure"] : FALSE,
                (array_key_exists('http', $cookie)) ? $cookie["http"] : FALSE
            );
        }
        }
    }

    /**
     * Método que inyecta las cabeceras necesarias para la autenticación
     */
    protected function setAuthHeaders() {
        if ($this->public_zone) {
            unset($_SERVER["PHP_AUTH_USER"]);
            unset($_SERVER["PHP_AUTH_PW"]);
            header_remove("Authorization");
        }else {
            header('Authorization:');
        }
    }

    /**
     * Método que establece el status code
     */
    protected function setStatusHeader() {
        if (NULL !== $this->status_code) {
            header($this->status_code);
        }
    }

    /**
     * Método que mete en las variables de las plantillas las cabeceras de debug
     * @param array $vars
     *
     * @return array
     */
    protected function setDebugHeaders(array $vars)
    {
        if ($this->debug) {
            Logger::log('Adding debug headers to render response');
            $vars["__DEBUG__"]["includes"] = get_included_files();
            $vars["__DEBUG__"]["trace"] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            header('X-PSFS-DEBUG-TS: '.Dispatcher::getInstance()->getTs().' s');
            header('X-PSFS-DEBUG-MEM: '.Dispatcher::getInstance()->getMem('MBytes').' MBytes');
            header('X-PSFS-DEBUG-FILES: '.count(get_included_files()).' files opened');
        }

        return $vars;
    }

    /**
     * Método que añade todas las funciones de las plantillas
     */
    private function addTemplateFunctions() {
        //Asignamos las funciones especiales
        $this->addAssetFunction()
            ->addFormsFunction()
            ->addFormWidgetFunction()
            ->addFormButtonFunction()
            ->addConfigFunction()
            ->addRouteFunction()
            ->addSessionFunction()
            ->addExistsFlashFunction()
            ->addGetFlashFunction()
            ->addResourceFunction()
        ;
    }

    /**
     * Método que devuelve el motod de plantillas
     * @return \Twig_Environment
     */
    public function getTemplateEngine() {
        return $this->tpl;
    }

    /**
     * Método que inicializa el motor de plantillas
     */
    private function setup() {
        $this->debug = Config::getInstance()->getDebugMode() ?: FALSE;
        $this->security = Security::getInstance();
        $this->cache = Cache::getInstance();
        $loader = new \Twig_Loader_Filesystem(Config::getInstance()->getTemplatePath());
        $this->tpl = new \Twig_Environment($loader, array(
            'cache'       => Config::getInstance()->getCachePath(),
            'debug'       => (bool)$this->debug,
            'auto_reload' => TRUE,
        ));
    }

    /**
     * Método que inyecta los parseadores
     */
    private function addTemplateTokens() {
        //Añadimos las extensiones de los tags
        $this->tpl->addTokenParser(new AssetsTokenParser("css"));
        $this->tpl->addTokenParser(new AssetsTokenParser("js"));
    }

    /**
     * Método que inyecta las optimizaciones al motor de la plantilla
     */
    private function optimizeTemplates() {
        //Optimizamos
        $this->tpl->addExtension(new \Twig_Extensions_Extension_I18n());
    }

    /**
     * Method that extract all path tag for extracting translations
     * @param array $domains
     *
     * @return array
     */
    private function parsePathTranslations($domains)
    {
        $translations = array();
        if (!empty($domains)) {
            foreach ($domains as $domain => $paths) {
                if (strlen($domain) && array_key_exists("template", $paths)) {
                    $this->addPath($paths["template"], $domain);
                    $translations[] = $this->generateTemplate($paths["template"], $domain);
                }
            }
        }

        return $translations;
    }

    /**
     * Method that generate all template caches
     */
    private function generateTemplatesCache()
    {
        /** @var \Twig_Loader_Filesystem $loader */
        $loader = $this->tpl->getLoader();
        $availablePaths = $loader->getPaths();
        if (!empty($availablePaths)) {
            foreach ($availablePaths as $path) {
                $this->generateTemplate($path);
            }
        }
    }
}
