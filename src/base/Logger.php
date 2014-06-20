<?php

namespace PSFS\base;

use PSFS\base\Singleton;
use PSFS\exception\LoggerException;
use PSFS\base\Request as Parser;

if(!defined("LOG_DIR"))
{
    if(!file_exists(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR)) @mkdir(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR);
    define("LOG_DIR", realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR));
}

class Logger extends Singleton{

    const INFO_LOG = 0;
    const DEBUG_LOG = 1;
    const ERROR_LOG = 2;

    private $info;
    private $debug;
    private $error;

    protected $path;
    protected $date;
    protected $ts;
    protected $isDebug = false;

    public function __construct($path = LOG_DIR)
    {
        if(!file_exists($path)) @mkdir($path);
        $this->path = $path.DIRECTORY_SEPARATOR;
        $this->date = date("Ymd");
        $this->ts = Parser::getInstance()->ts();
    }

    /**
     * Método que escribe el log en el fichero
     *
     * @param $msg
     * @param int $type
     *
     * @return bool
     */
    private function _write($msg, $type = Logger::INFO_LOG)
    {
        $write = false;
        try{
            $dump = "[{$this->ts}]";
            switch($type)
            {
                case 0:
                default:$dump .= "[INFO] "; break;
                case 1: $dump .= "[DEBUG] "; break;
                case 2: $dump .= "[ERROR] "; break;
            }
            $dump .= print_r($msg, true);
            $file = fopen($this->path.$this->date.".log", "a+");
            fwrite($file, $dump.PHP_EOL);
            fclose($file);

        }catch(LoggerException $e){
            echo $e->getError();
        }catch(Exception $e){
            echo $e->getMessage();
        }
        return $write;
    }

    /**
     * Método que escribe un log de información
     * @param string $msg
     *
     * @return bool
     */
    public function infoLog($msg = '')
    {
        return $this->_write($msg);
    }

    /**
     * Método que escribe un log de Debug
     * @param string $msg
     *
     * @return bool
     */
    public function debugLog($msg = '')
    {
        return $this->_write($msg, Logger::DEBUG_LOG);
    }

    /**
     * Método que escribe un log de Error
     * @param $msg
     *
     * @return bool
     */
    public function errorLog($msg)
    {
        return $this->_write($msg, Logger::ERROR_LOG);
    }
}