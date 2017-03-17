<?php
namespace PSFS\base\types;

use PSFS\base\exception\RouterException;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\interfaces\ControllerInterface;
use PSFS\base\types\traits\JsonTrait;
use PSFS\base\types\traits\OutputTrait;
use PSFS\base\types\traits\RouteTrait;

/**
 * Class Controller
 * @package PSFS\base\types
 */
abstract class Controller extends Singleton implements ControllerInterface
{
    use JsonTrait;
    use RouteTrait;

    /**
     * @Injectable
     * @var \PSFS\base\Template $tpl
     */
    protected $tpl;
    protected $domain = '';

    /**
     * Método que renderiza una plantilla
     * @param string $template
     * @param array $vars
     * @param array $cookies
     * @param string $domain
     *
     * @return string HTML
     */
    public function render($template, array $vars = array(), $cookies = array(), $domain = null)
    {
        $vars['__menu__'] = $this->getMenu();
        $domain = (null === $domain) ? $this->getDomain() : $domain;
        return $this->tpl->render($domain . $template, $vars, $cookies);
    }

    /**
     * Método del controlador que añade los menús automáticamente a las vistas
     * @return array
     */
    protected function getMenu()
    {
        return array();
    }

    public function init()
    {
        parent::init();
        $this->setDomain($this->domain)
            ->setTemplatePath(GeneratorHelper::getTemplatePath());
    }

    /**
     * Método que renderiza una plantilla
     * @param string $template
     * @param array $vars
     * @param string $domain
     *
     * @return string
     */
    public function dump($template, array $vars = array(), $domain = null)
    {
        $vars['__menu__'] = $this->getMenu();
        $domain = $domain ?: $this->getDomain();
        return $this->tpl->dump($domain . $template, $vars);
    }

    /**
     * Método que añade la ruta del controlador a los path de plantillas Twig
     * @param string $path
     * @return $this
     */
    protected function setTemplatePath($path)
    {
        $this->tpl->addPath($path, $this->domain);
        return $this;
    }

    /**
     * Método que setea el dominio del controlador para las plantillas
     * @param string $domain
     *
     * @return $this
     */
    protected function setDomain($domain)
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * Método que devuelve el dominio del controlador
     * @return string
     */
    public function getDomain()
    {
        return "@{$this->domain}/";
    }

}
