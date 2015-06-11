<?php

namespace PSFS\base\types\interfaces;

/**
 * Interface ControllerInterface
 * @package PSFS\base\types\interfaces
 */
interface ControllerInterface{
    public function render($template, Array $vars = null);
    public function getModel($model);
    public function response($response, $type = "text/html");
}
