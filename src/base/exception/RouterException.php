<?php
namespace PSFS\base\exception;

/**
 * Class RouterException
 * @package PSFS\base\exception
 */
class RouterException extends \Exception{
    /**
     * @param null $message
     * @return $this
     */
    public function __construct($message = null)
    {
        parent::__construct($message ?: _("Página no encontrada"), 404);
    }
}