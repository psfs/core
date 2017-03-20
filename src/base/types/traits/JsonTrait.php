<?php
namespace PSFS\base\types\traits;
use PSFS\base\config\Config;

/**
 * Class JsonTrait
 * @package PSFS\base\types\traits
 */
Trait JsonTrait {
    use OutputTrait;

    /**
     * Método que devuelve una salida en formato JSON
     * @param mixed $response
     * @param int $statusCode
     *
     * @return string JSON
     */
    public function json($response, $statusCode = 200)
    {
        $data = json_encode($response, JSON_UNESCAPED_UNICODE);
        if(Config::getParam('angular.protection', false)) {
            $data = ")]}',\n" . $data;
        }
        $this->setStatus($statusCode);
        return $this->output($data, "application/json");
    }

    /**
     * Método que devuelve una salida en formato JSON
     * @param mixed $response
     */
    public function jsonp($response)
    {
        $data = json_encode($response, JSON_UNESCAPED_UNICODE);
        $this->output($data, "application/javascript");
    }
}