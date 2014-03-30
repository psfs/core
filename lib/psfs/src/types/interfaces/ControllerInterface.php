<?php

namespace PSFS\types\interfaces;

interface ControllerInterface{
    public function render($template, Array $vars = null);
    public function getModel($model);
    public function response(string $response, $type = "text/html");
}