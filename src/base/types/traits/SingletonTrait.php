<?php

namespace PSFS\base\types\traits;

/**
 * Class SingletonTrait
 * @package PSFS\base\types
 */
trait SingletonTrait
{
    use BoostrapTrait;

    /**
     * @var boolean
     */
    private $loaded = false;

    /**
     * @var array Singleton cached reference to singleton instance
     */
    private static $instance = [];

    /**
     * gets the instance via lazy initialization (created on first usage)
     *
     * @param array $args
     * @return $this
     */
    public static function getInstance(...$args)
    {
        $class = static::class;
        if (!array_key_exists($class, self::$instance) || !self::$instance[$class] instanceof $class) {
            self::$instance[$class] = new $class($args[0] ?? null);
            self::initiate(self::$instance[$class]);
        }
        return self::$instance[$class];
    }

    /**
     * drop the instance
     */
    public static function dropInstance()
    {
        $class = static::class;
        if (isset(self::$instance[$class])) {
            self::$instance[$class] = null;
        }
    }

    /**
     * @return bool
     */
    public function isLoaded()
    {
        return $this->loaded;
    }

    /**
     * @param bool $loaded
     */
    public function setLoaded($loaded = true)
    {
        $this->loaded = $loaded;
    }

    /**
     * Try to initiate the class only once
     * @param mixed $instance
     * @param mixed $args
     */
    private static function initiate($instance, $args = null)
    {
        $loaded = false;
        if (method_exists($instance, 'isLoaded')) {
            $loaded = $instance->isLoaded();
        }
        if (false === $loaded && method_exists($instance, 'init')) {
            $instance->init($args);
        }
    }

}
