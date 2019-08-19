<?php

namespace PSFS\base\exception;

/**
 * Class ConfigException
 * @package PSFS\base\exception
 */
class ConfigException extends \RuntimeException
{
    /**
     * @param string $message
     * @param integer $code
     */
    public function __construct($message = null, $code = 500)
    {
        $this->code = $code;
        $this->message = $message ?: t("Error en la configuración de la plataforma");
    }
}
