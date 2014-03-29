<?php

namespace PSFS\types;

use PSFS\types\interfaces\ControllerInterface;

/**
 * Class Controller
 * @package PSFS\types
 */
class Controller implements ControllerInterface{

    public function render($template, array $vars = array())
    {

    }

    public function getModel($model)
    {

    }

    public function response($response, $type = "text/html")
    {

    }
}