<?php

namespace PSFS\base\types\traits;

use PSFS\base\SingletonRegistry;

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
     * gets the instance via lazy initialization (created on first usage)
     *
     * @param array $args
     * @return $this
     */
    public static function getInstance(...$args)
    {
        $class = static::class;
        $instance = SingletonRegistry::get($class);
        if(!$instance) {
            $instance = new $class($args[0] ?? null);
            SingletonRegistry::register($instance);
            self::initiate($instance);
        }
        return $instance;
    }

    /**
     * drop the instance
     */
    public static function dropInstance()
    {
        $class = static::class;
        SingletonRegistry::drop($class);
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
