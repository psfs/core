<?php

namespace PSFS\base\exception;

/**
 * Class ConfigException
 * @package PSFS\base\exception
 */
class ConfigException extends \RuntimeException
{
    /**
     * @param null $message
     */
    public function __construct($message = null)
    {
        $this->code = 500;
        $this->message = $message ?: _("Error en la configuración de la plataforma");
    }
}
