<?php
namespace PSFS\base\exception;

/**
 * Class SecurityException
 * @package PSFS\base\exception
 */
class SecurityException extends \Exception{
    /**
     * @param null $message
     * @return $this
     */
    public function __construct($message = null)
    {
        parent::__construct($message ?: _("Not authorized"), 401);
    }
}