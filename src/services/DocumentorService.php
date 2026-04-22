<?php

namespace PSFS\services;

use Exception;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use PSFS\base\dto\Dto;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\AnnotationHelper;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\SimpleService;
use PSFS\base\types\traits\Api\DocumentorHelperTrait;
use PSFS\base\types\traits\Api\SwaggerFormaterTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * @package PSFS\services
 */
class DocumentorService extends SimpleService
{
    use DocumentorHelperTrait;
    use SwaggerFormaterTrait;

    const DTO_INTERFACE = Dto::class;
    const MODEL_INTERFACE = ActiveRecordInterface::class;

    /**
     * @var \PSFS\base\Router
     */
    #[Injectable(class: Router::class)]
    protected Router $route;

    /**
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
     * Build a normalized endpoint specification model used by all output formatters.
     * Current v1 model reuses extracted endpoint metadata and keeps legacy shape.
     *
     * @param array $module
     * @return array
     * @throws ReflectionException
     */
    public function buildEndpointSpec(array $module): array
    {
        return $this->extractApiEndpoints($module);
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
            $visible = AnnotationHelper::extractReflectionVisibility((string)$reflection->getDocComment(), $reflection);
            if ($visible && $reflection->isInstantiable()) {
                foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    try {
                        $mInfo = $this->extractMethodInfo($namespace, $method, $reflection, $module);
                        if (null !== $mInfo) {
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
