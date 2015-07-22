<?php

    namespace PSFS\base\types;

    /**
     * Class SingletonTrait
     * @package PSFS\base\types
     */
    Trait SingletonTrait {
        /**
         * @var array Singleton cached reference to singleton instance
         */
        protected static $instance = array();

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
                if(method_exists(self::$instance[$class], "init")) self::$instance[$class]->init();
            }
            return self::$instance[$class];
        }
    }