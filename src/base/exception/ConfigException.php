<?php

namespace PSFS\base\exception;

/**
 * Class ConfigException
 * @package PSFS\base\exception
 */
class ConfigException extends \RuntimeException
{
    /**
     * @param string|null $message
     * @param integer $code
     */
    public function __construct(string $message = null, int $code = 500)
    {
        parent::__construct($message, $code);
        $this->code = $code;
        $this->message = $message ?: t("Error en la configuración de la plataforma");
    }
}
