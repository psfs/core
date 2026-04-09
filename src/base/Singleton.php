<?php

namespace PSFS\base;

use Exception;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\reflection\FileReflectionCacheRepository;
use PSFS\base\reflection\RedisReadThroughReflectionCacheRepository;
use PSFS\base\reflection\ReflectionCacheRepositoryInterface;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\traits\SingletonTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * @package PSFS\base
 */
class Singleton
{
    use SingletonTrait;

    /**
     * @throws Exception
     * @throws exception\GeneratorException
     * @throws ConfigException
     */
    public function __construct()
    {
        Inspector::stats(static::class . ' constructor invoked');
        $this->init();
    }

    /**
     * @param string $variable
     * @param mixed $value
     */
    public function __set($variable, $value)
    {
        if ($this->__isset($variable)) {
            $this->$variable = $value;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return property_exists(get_class($this), $name);
    }

    /**
     * @param string $variable
     * @return mixed
     */
    public function __get($variable)
    {
        return $this->__isset($variable) ? $this->$variable : null;
    }


    /**
     * @return string
     * @throws ReflectionException
     */
    public function getShortName()
    {
        $reflector = new ReflectionClass(get_class($this));
        return $reflector->getShortName();
    }

    /**
     * @param string $variable
     * @param bool $singleton
     * @param string $classNameSpace
     * @return $this
     * @throws Exception
     */
    public function load($variable, $singleton = true, $classNameSpace = null, bool $required = true)
    {
        $calledClass = static::class;
        try {
            $instance = InjectorHelper::constructInjectableInstance(
                $variable,
                $singleton,
                $classNameSpace,
                $calledClass
            );
            $setter = 'set' . ucfirst($variable);
            if (method_exists($calledClass, $setter)) {
                $this->$setter($instance);
            } else {
                $this->$variable = $instance;
            }
        } catch (Exception $e) {
            Logger::log($e->getMessage() . ': ' . $e->getFile() . ' [' . $e->getLine() . ']', LOG_ERR);
            if (!$required) {
                Logger::log('[Injectable][optional] Skipping optional dependency: ' . $variable, LOG_WARNING);
                return $this;
            }
            throw $e;
        }
        return $this;
    }

    /**
     * @throws Exception
     * @throws exception\GeneratorException
     * @throws ConfigException
     */
    public function init()
    {
        if (!$this->isLoaded()) {
            $configService = Config::getInstance();
            $repository = $this->createReflectionRepository(get_class($this));
            $properties = $repository->read();
            if (!$properties || true === $configService->getDebugMode()) {
                $properties = InjectorHelper::getClassProperties(get_class($this));
                $repository->save($properties);
            }

            if (!empty($properties) && is_array($properties)) {
                foreach ($properties as $property => $cachedDefinition) {
                    $definition = InjectorHelper::resolveInjectableRuntimeDefinition(
                        get_class($this),
                        (string)$property,
                        $cachedDefinition
                    );
                    if (($definition['isInjectable'] ?? false) !== true) {
                        continue;
                    }
                    $this->load(
                        $property,
                        (bool)$definition['singleton'],
                        $definition['class'],
                        (bool)$definition['required']
                    );
                }
            }
            $this->setLoaded();
        } else {
            Logger::log(get_class($this) . ' already loaded', LOG_INFO);
        }
    }

    protected function createReflectionRepository(string $className): ReflectionCacheRepositoryInterface
    {
        $fileRepository = new FileReflectionCacheRepository($className);
        if (Cache::canUseRedis()) {
            $ttl = (int)Config::getParam('cache.reflections.ttl', 300);
            $version = (string)Config::getParam('cache.var', 'v1');
            return new RedisReadThroughReflectionCacheRepository($fileRepository, $ttl, $version);
        }
        return $fileRepository;
    }
}
