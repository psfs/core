<?php

namespace PSFS\services;

use Exception;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use PSFS\base\dto\Dto;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\SimpleService;
use PSFS\base\types\traits\Api\DocumentorHelperTrait;
use PSFS\base\types\traits\Api\SwaggerFormaterTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Class DocumentorService
 * @package PSFS\services
 */
class DocumentorService extends SimpleService
{
    use DocumentorHelperTrait;
    use SwaggerFormaterTrait;

    const DTO_INTERFACE = Dto::class;
    const MODEL_INTERFACE = ActiveRecordInterface::class;

    /**
     * @Injectable
     * @var \PSFS\base\Router route
     */
    protected $route;

    /**
     * Method that extract all endpoints for each module
     * @param array $module
     * @return array
     * @throws ReflectionException
     */
    public function extractApiEndpoints(array $module)
    {
        $modulePath = $module['path'] . DIRECTORY_SEPARATOR . 'Api';
        $moduleName = $module['name'];
        $endpoints = [];
        if (file_exists($modulePath)) {
            $finder = new Finder();
            $finder->files()->in($modulePath)->depth('< 2')->name('*.php');
            if (count($finder)) {
                /** @var SplFileInfo $file */
                foreach ($finder as $file) {
                    $filename = str_replace([$modulePath, '/'], ['', '\\'], $file->getPathname());
                    $namespace = "\\{$moduleName}\\Api" . str_replace('.php', '', $filename);
                    $info = $this->extractApiInfo($namespace, $moduleName);
                    if (!empty($info)) {
                        $endpoints[$namespace] = $info;
                    }
                }
            }
        }
        return $endpoints;
    }

    /**
     * @param $namespace
     * @param $module
     * @return array
     * @throws ReflectionException
     */
    public function extractApiInfo($namespace, $module)
    {
        $info = [];
        if (Router::exists($namespace) && !I18nHelper::checkI18Class($namespace)) {
            $reflection = new ReflectionClass($namespace);
            $visible = InjectorHelper::checkIsVisible($reflection->getDocComment());
            if ($visible && $reflection->isInstantiable()) {
                foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    try {
                        $mInfo = $this->extractMethodInfo($namespace, $method, $reflection, $module);
                        if (NULL !== $mInfo) {
                            $info[] = $mInfo;
                        }
                    } catch (Exception $e) {
                        Logger::log($e->getMessage(), LOG_ERR);
                    }
                }
            }
        }
        return $info;
    }
}
