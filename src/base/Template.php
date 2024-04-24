<?php

namespace PSFS\base;

use PSFS\base\config\Config;
use PSFS\base\extension\AssetsTokenParser;
use PSFS\base\extension\CustomTranslateExtension;
use PSFS\base\extension\TemplateFunctions;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\traits\OutputTrait;
use PSFS\base\types\traits\RouteTrait;
use PSFS\base\types\traits\SingletonTrait;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Class Template
 * @package PSFS\base
 */
class Template
{
    use SingletonTrait;
    use OutputTrait;
    use RouteTrait;

    const STATUS_OK = 'HTTP/1.0 200 OK';
    /**
     * @var \Twig\Environment tpl
     */
    protected $tpl;
    protected $filters = array();

    /**
     * Constructor por defecto
     */
    public function __construct()
    {
        $this->setup();
        $this->addTemplateFunctions();
        $this->addTemplateTokens();
        $this->optimizeTemplates();
    }

    /**
     * Método que devuelve el loader del Template
     * @return \Twig\Loader\LoaderInterface
     */
    public function getLoader()
    {
        return $this->tpl->getLoader();
    }

    /**
     * Método que activa la zona pública
     * @param bool $public
     *
     * @return Template
     */
    public function setPublicZone($public = true)
    {
        $this->public_zone = $public;
        return $this;
    }

    /**
     * @return bool
     */
    public function isPublicZone()
    {
        return $this->public_zone;
    }

    /**
     * @param string $tpl
     * @param array $vars
     * @param array $cookies
     * @return string HTML
     * @throws exception\GeneratorException
     */
    public function render($tpl, array $vars = array(), array $cookies = array())
    {
        Logger::log('Start render response');
        $vars = ResponseHelper::setDebugHeaders($vars);
        $output = $this->dump($tpl, $vars);

        return $this->output($output, 'text/html', $cookies);
    }

    /**
     * Método que añade una nueva ruta al path de Twig
     * @param $path
     * @param $domain
     *
     * @return Template
     */
    public function addPath($path, $domain = '')
    {
        if (file_exists($path)) {
            $this->tpl->getLoader()->addPath($path, $domain);
        }
        return $this;
    }

    /**
     * Método que devuelve el contenido de una plantilla
     * @param string $tpl
     * @param array $vars
     * @return string
     */
    public function dump($tpl, array $vars = array())
    {
        $vars['__user__'] = Security::getInstance()->getUser();
        $vars['__admin__'] = Security::getInstance()->getAdmin();
        $vars['__profiles__'] = Security::getCleanProfiles();
        $vars['__flash__'] = Security::getInstance()->getFlashes();
        $vars['__get__'] = Request::getInstance()->getQueryParams();
        $vars['__post__'] = Request::getInstance()->getData();
        $dump = '';
        try {
            $dump = $this->tpl->render($tpl, $vars);
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
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
    protected function addTemplateFunction($templateFunction, $functionName)
    {
        $function = new TwigFunction($templateFunction, $functionName);
        $this->tpl->addFunction($function);
        return $this;
    }

    /**
     * Servicio que regenera todas las plantillas
     * @return array
     */
    public function regenerateTemplates()
    {
        $this->generateTemplatesCache();
        $domains = Cache::getInstance()->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json', Cache::JSON, true);
        $translations = [];
        if (is_array($domains)) {
            $translations = $this->parsePathTranslations($domains);
        }
        $translations[] = t('Plantillas regeneradas correctamente');
        return $translations;
    }

    /**
     * @param $tplDir
     * @param string $domain
     *
     * @return mixed
     */
    protected function generateTemplate($tplDir, $domain = '')
    {
        if (!file_exists($tplDir)) {
            return str_replace(array('%s', '%d'), array($tplDir, $domain), t('Path "%s" no existe para el dominio "%d"'));
        }
        $templatesDir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tplDir), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($templatesDir as $file) {
            // force compilation
            if ($file->isFile()) {
                try {
                    $this->tpl->load(str_replace($tplDir . '/', '', $file));
                } catch (\Exception $e) {
                    Logger::log($e->getMessage(), LOG_ERR, ['file' => $e->getFile(), 'line' => $e->getLine()]);
                }
            }
        }
        return str_replace(array('%s', '%d'), array($tplDir, $domain), t('Generando plantillas en path "%s" para el dominio "%d"'));
    }

    /**
     * Método que extrae el path de un string
     * @param $path
     *
     * @return string
     */
    public static function extractPath($path)
    {
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
    static public function getDomains($append = false)
    {
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
     * Método que añade todas las funciones de las plantillas
     */
    private function addTemplateFunctions()
    {
        //Asignamos las funciones especiales
        $functions = [
            'asset' => TemplateFunctions::ASSETS_FUNCTION,
            'form' => TemplateFunctions::FORM_FUNCTION,
            'form_widget' => TemplateFunctions::WIDGET_FUNCTION,
            'form_button' => TemplateFunctions::BUTTON_FUNCTION,
            'get_config' => TemplateFunctions::CONFIG_FUNCTION,
            'path' => TemplateFunctions::ROUTE_FUNCTION,
            'resource' => TemplateFunctions::RESOURCE_FUNCTION,
            'session' => TemplateFunctions::SESSION_FUNCTION,
            'existsFlash' => TemplateFunctions::EXISTS_FLASH_FUNCTION,
            'getFlash' => TemplateFunctions::GET_FLASH_FUNCTION,
            'getQuery' => TemplateFunctions::GET_QUERY_FUNCTION,
            'encrypt' => TemplateFunctions::ENCRYPT_FUNCTION,
            'generate_auth_token' => TemplateFunctions::AUTH_TOKEN_FUNCTION,
        ];
        foreach ($functions as $name => $function) {
            $this->addTemplateFunction($name, $function);
        }
    }

    /**
     * Método que devuelve el motod de plantillas
     * @return \Twig\Environment
     */
    public function getTemplateEngine()
    {
        return $this->tpl;
    }

    /**
     * Method that extract all domains for using them with the templates
     */
    private function loadDomains()
    {
        $domains = Cache::getInstance()->getDataFromFile(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json', Cache::JSON, true);
        if (null !== $domains) {
            foreach ($domains as $domain => $paths) {
                $this->addPath($paths['template'], preg_replace('/(@|\/)/', '', $domain));
            }
        }
    }

    /**
     * Método que inicializa el motor de plantillas
     */
    private function setup()
    {
        $loader = new FilesystemLoader(GeneratorHelper::getTemplatePath());
        $this->tpl = new Environment($loader, array(
            'cache' => CACHE_DIR . DIRECTORY_SEPARATOR . 'twig',
            'debug' => (bool)$this->debug,
            'auto_reload' => Config::getParam('twig.autoreload', TRUE),
        ));
        $this->loadDomains();
    }

    /**
     * Método que inyecta los parseadores
     */
    private function addTemplateTokens()
    {
        //Añadimos las extensiones de los tags
        $this->tpl->addTokenParser(new AssetsTokenParser('css'));
        $this->tpl->addTokenParser(new AssetsTokenParser('js'));
    }

    /**
     * Método que inyecta las optimizaciones al motor de la plantilla
     */
    private function optimizeTemplates()
    {
        //Optimizamos
        $this->tpl->addExtension(new CustomTranslateExtension());
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
                if (strlen($domain) && array_key_exists('template', $paths)) {
                    $this->addPath($paths['template'], $domain);
                    $translations[] = $this->generateTemplate($paths['template'], $domain);
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
        /** @var \Twig\Loader\FilesystemLoader $loader */
        $loader = $this->tpl->getLoader();
        $availablePaths = $loader->getPaths();
        if (!empty($availablePaths)) {
            foreach ($availablePaths as $path) {
                $this->generateTemplate($path);
            }
        }
    }
}
