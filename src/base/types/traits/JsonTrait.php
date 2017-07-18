<?php
namespace PSFS\base\types\traits;
use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\dto\ProfilingJsonResponse;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\Inspector;

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
     * @return mixed JSON
     */
    public function json($response, $statusCode = 200)
    {
        if(Config::getParam('json.encodeUTF8', false)) {
            $response = I18nHelper::utf8Encode($response);
        }
        if(Config::getParam('profiling.enable')) {
            if(is_array($response)) {
                $response['profiling'] = Inspector::getStats();
            } elseif($response instanceof JsonResponse) {
                $response = ProfilingJsonResponse::createFromPrevious($response, Inspector::getStats());
            }
        }
        $data = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
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