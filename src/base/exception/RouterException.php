<?php
namespace PSFS\base\exception;

class RouterException extends \Exception{
    public function __construct($message = null)
    {
        parent::__construct($message ?: _("Página no encontrada"), 404);
    }
}