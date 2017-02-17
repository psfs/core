<?php

namespace PSFS\base\exception;

/**
 * Class LoggerException
 * @package PSFS\base\exception
 */
class LoggerException extends \Exception
{

    /**
     * @return string
     */
    public function getError()
    {
        $msg = $this->getMessage();
        return "<p style='width: 80%;margin:0 auto;color:red;font-size:12px;font-weight:bolder;font-family:Arial;padding:10px;display:block;border:1px solid #610B0B;background:#F5A9A9;'>{$msg}</p>";
    }
}
