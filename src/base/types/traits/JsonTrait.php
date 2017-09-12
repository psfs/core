<?php
namespace PSFS\base\types\traits;
use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\dto\ProfilingJsonResponse;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\ResponseHelper;

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
        if(Config::getParam('profiling.enable')) {
            if(is_array($response)) {
                $response['profiling'] = Inspector::getStats();
            } elseif($response instanceof JsonResponse) {
                $response = ProfilingJsonResponse::createFromPrevious($response, Inspector::getStats());
            }
        }

        $this->decodeJsonReponse($response);

        $data = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_BIGINT_AS_STRING);
        if(Config::getParam('angular.protection', false)) {
            $data = ")]}',\n" . $data;
        }
        $this->setStatus($statusCode);
        ResponseHelper::setDebugHeaders([]);
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

    /**
     * @param $response
     * @return array|string
     */
    private function decodeJsonReponse(&$response)
    {
        if (Config::getParam('json.encodeUTF8', false)) {
            $response = I18nHelper::utf8Encode($response);
        }

        if ($response instanceof JsonResponse) {
            $response = $response->toArray();
        }
    }
}