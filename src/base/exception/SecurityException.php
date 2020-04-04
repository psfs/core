<?php

namespace PSFS\base\exception;

/**
 * Class SecurityException
 * @package PSFS\base\exception
 */
class SecurityException extends \Exception
{
    /**
     * @param string|null $message
     */
    public function __construct($message = null)
    {
        parent::__construct($message ?: t("Not authorized"), 401);
    }
}
