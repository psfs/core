<?php

namespace PSFS\base\types;

use PSFS\base\config\Config;
use PSFS\base\Singleton;
use PSFS\base\Template;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\interfaces\ControllerInterface;
use PSFS\base\types\traits\JsonTrait;
use PSFS\base\types\traits\RouteTrait;

/**
 * @package PSFS\base\types
 */
abstract class Controller extends Singleton implements ControllerInterface
{
    use JsonTrait;
    use RouteTrait;

    #[Injectable(class: Template::class)]
    protected $tpl;
    protected $domain = 'ROOT';

    /**
     * @param string $template
     * @param array $vars
     * @param array $cookies
     * @param string $domain
     *
     * @return string
     */
    public function render($template, array $vars = array(), $cookies = array(), $domain = null)
    {
        $domain = $this->resolveRenderDomain($domain);
        $vars = $this->buildTemplateVars($vars, true);
        return $this->tpl->render($domain . $template, $vars, $cookies);
    }

    /**
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
     * @param string $template
     * @param array $vars
     * @param string $domain
     *
     * @return string
     */
    public function dump($template, array $vars = array(), $domain = null)
    {
        $vars = $this->buildTemplateVars($vars, false);
        $domain = $this->resolveRenderDomain($domain);
        return $this->tpl->dump($domain . $template, $vars);
    }

    /**
     * @param string $path
     * @return $this
     */
    protected function setTemplatePath($path)
    {
        $this->tpl->addPath($path, $this->domain);
        return $this;
    }

    /**
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
     * @return string
     */
    public function getDomain()
    {
        return "@{$this->domain}/";
    }

    private function resolveRenderDomain(?string $domain): string
    {
        return $domain ?? $this->getDomain();
    }

    private function buildTemplateVars(array $vars, bool $includeProfiling): array
    {
        $vars['__menu__'] = $this->getMenu();
        if ($includeProfiling && Config::getParam('profiling.enable')) {
            $vars['__profiling__'] = Inspector::getStats();
        }

        return $vars;
    }

}
