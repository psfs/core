<?php
namespace PSFS\base\exception;

/**
 * Class RouterException
 * @package PSFS\base\exception
 */
class RouterException extends \RuntimeException {
    /**
     * @param null $message
     */
    public function __construct($message = null, $code = 404, \Exception $e = null)
    {
        parent::__construct($message ?: _("Página no encontrada"), $code, $e);
    }
}
