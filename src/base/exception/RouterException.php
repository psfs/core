<?php

namespace PSFS\base\exception;

/**
 * @package PSFS\base\exception
 */
class RouterException extends \RuntimeException
{
    /**
     * @param string $message
     * @param integer $code
     * @param \Throwable $exception
     */
    public function __construct($message = null, $code = 404, ?\Throwable $exception = null)
    {
        parent::__construct($message ?: t("Page not found"), $code, $exception);
    }
}
