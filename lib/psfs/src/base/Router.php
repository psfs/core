<?php

namespace PSFS\base;

use PSFS\base\Singleton;

/**
 * Class Router
 * @package PSFS
 */
class Router extends Singleton{

    public function httpNotFound()
    {
        header("HTTP/1.0 404 Not Found");
        exit();
    }
}