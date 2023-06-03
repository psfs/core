<?php
namespace PSFS\base\types\traits;

/**
 * Trait TestTrait
 * @package PSFS\base\types\traits
 */
trait TestTrait {
    /**
     * @var bool
     */
    private static $test = false;

    /**
     * @return bool
     */
    public static function isTest()
    {
        return defined('PSFS_UNIT_TESTING_EXECUTION') ? self::$test : false;
    }

    /**
     * @param bool $test
     */
    public static function setTest(bool $test): void
    {
        self::$test = $test;
    }


}