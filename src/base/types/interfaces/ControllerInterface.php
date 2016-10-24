<?php

namespace PSFS\base\types\interfaces;

/**
 * Interface ControllerInterface
 * @package PSFS\base\types\interfaces
 */
interface ControllerInterface {
    public function render($template, array $vars = [], $cookies = []);
    public function response($response, $type = 'text/html');
}
