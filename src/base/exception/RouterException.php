<?php

namespace PSFS\base\exception;

/**
 * Class RouterException
 * @package PSFS\base\exception
 */
class RouterException extends \RuntimeException
{
    /**
     * @param string $message
     * @param integer $code
     * @param \Exception $exception
     */
    public function __construct($message = null, $code = 404, \Exception $exception = null)
    {
        parent::__construct($message ?: t("Página no encontrada"), $code, $exception);
    }
}
