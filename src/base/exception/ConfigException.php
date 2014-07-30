<?php

namespace PSFS\base\exception;

class ConfigException extends \Exception{
    public function __construct($message = null)
    {
        $this->code = 500;
        $this->message = $message ?: _("Error en la configuración de la plataforma");
    }
}