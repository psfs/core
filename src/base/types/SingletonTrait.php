<?php

namespace PSFS\base\types;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';
/**
 * Class SingletonTrait
 * @package PSFS\base\types
 */
Trait SingletonTrait
{
    /**
     * @var array Singleton cached reference to singleton instance
     */
    protected static $instance = [];

    /**
     * gets the instance via lazy initialization (created on first usage)
     *
     * @return $this
     */
    public static function getInstance()
    {
        $class = get_called_class();
        if (!array_key_exists($class, self::$instance) || !self::$instance[$class] instanceof $class) {
            self::$instance[$class] = new $class(func_get_args());
            self::__init(self::$instance[$class]);
        }
        return self::$instance[$class];
    }

    /**
     * Try to initiate the class only once
     * @param mixed $instance
     */
    private static function __init($instance)
    {
        $loaded = false;
        if (method_exists($instance, 'isLoaded')) {
            $loaded = $instance->isLoaded();
        }
        if (false === $loaded && method_exists($instance, "init")) {
            $instance->init();
        }
    }
}