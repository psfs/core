<?php
namespace PSFS\base\types\traits;

/**
 * Class SingletonTrait
 * @package PSFS\base\types
 */
Trait SingletonTrait
{
    use BoostrapTrait;

    /**
     * @var boolean
     */
    protected $loaded = false;

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
     * drop the instance
     * @return mixed
     */
    public static function dropInstance() {
        $class = get_called_class();
        if(isset(self::$instance[$class])) {
            self::$instance[$class] = null;
        }
    }

    /**
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * @param bool $loaded
     */
    public function setLoaded(bool $loaded)
    {
        $this->loaded = $loaded;
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