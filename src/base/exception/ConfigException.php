<?php

namespace PSFS\base\exception;

/**
 * Class ConfigException
 * @package PSFS\base\exception
 */
class ConfigException extends \Exception{
    /**
     * @param null $message
     * @return $this
     */
    public function __construct($message = null)
    {
        $this->code = 500;
        $this->message = $message ?: _("Error en la configuración de la plataforma");
    }
}