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
    public function __construct($message = null, $code = 500)
    {
        $this->code = $code;
        $this->message = $message ?: _("Error en la configuración de la plataforma");
    }
}
