<?php
namespace PSFS\base\types\traits;

if(!defined('PSFS_BOOSTRAP_TRAT_LOADED')) {
    /**
     * Class BoostrapTrait
     * @package PSFS\base\types
     */
    Trait BoostrapTrait
    {
    }
    define('PSFS_BOOSTRAP_TRAT_LOADED', true);
    require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';
}
