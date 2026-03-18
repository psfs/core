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
 * @package PSFS\base
 */
class Template
{
    use SingletonTrait;
    use OutputTrait;
    use RouteTrait;

    const STATUS_OK = 'HTTP/1.0 200 OK';
    /**
     * @var \Twig\Environment
     */
    protected $tpl;
    protected $filters = array();


    public function __construct()
    {
        $this->setup();
        $this->addTemplateFunctions();
        $this->addTemplateTokens();
        $this->optimizeTemplates();
    }

    /**
     * @return \Twig\Loader\LoaderInterface
     */
    public function getLoader()
    {
        return $this->tpl->getLoader();
    }

    /**
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
     * @return string
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
     * @return array
     */
    public function regenerateTemplates()
    {
        $this->generateTemplatesCache();
        $domains = Cache::getInstance()->getDataFromFile(
            CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json',
            Cache::JSON,
            true
        );
        $translations = [];
        if (is_array($domains)) {
            $translations = $this->parsePathTranslations($domains);
        }
        $translations[] = t('Templates regenerated successfully');
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
            return str_replace(array('%s', '%d'),
                array($tplDir, $domain),
                t('Path "%s" does not exist for domain "%d"'));
        }
        $templatesDir = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tplDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
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
        return str_replace(array('%s', '%d'),
            array($tplDir, $domain),
            t('Generating templates in path "%s" for domain "%d"'));
    }

    /**
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


    private function addTemplateFunctions()
    {
        // Register template helper functions.
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
            'generate_jwt_token' => TemplateFunctions::JWT_TOKEN_FUNCTION,
        ];
        foreach ($functions as $name => $function) {
            $this->addTemplateFunction($name, $function);
        }
    }

    /**
     * @return \Twig\Environment
     */
    public function getTemplateEngine()
    {
        return $this->tpl;
    }


    private function loadDomains()
    {
        $domains = Cache::getInstance()->getDataFromFile(
            CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json',
            Cache::JSON,
            true
        );
        if (null !== $domains) {
            foreach ($domains as $domain => $paths) {
                $this->addPath($paths['template'], preg_replace('/(@|\/)/', '', $domain));
            }
        }
    }


    private function setup()
    {
        $loader = new FilesystemLoader(GeneratorHelper::getTemplatePath());
        $this->tpl = new Environment($loader, array(
            'cache' => CACHE_DIR . DIRECTORY_SEPARATOR . 'twig',
            'debug' => (bool)$this->debug,
            'auto_reload' => Config::getParam('twig.autoreload', true),
        ));
        $this->loadDomains();
    }


    private function addTemplateTokens()
    {
        // Add token parser extensions.
        $this->tpl->addTokenParser(new AssetsTokenParser('css'));
        $this->tpl->addTokenParser(new AssetsTokenParser('js'));
    }


    private function optimizeTemplates()
    {
        // Enable template optimizations.
        $this->tpl->addExtension(new CustomTranslateExtension());
    }

    /**
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


    private function generateTemplatesCache()
    {
        $loader = $this->tpl->getLoader();
        $availablePaths = $loader->getPaths();
        if (!empty($availablePaths)) {
            foreach ($availablePaths as $path) {
                $this->generateTemplate($path);
            }
        }
    }
}
