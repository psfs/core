<?php
namespace PSFS\base\dto;

class ProfilingJsonResponse extends JsonResponse {
    public $profiling = [];

    public function setProfile(array $profiling) {
        $this->profiling = $profiling;
    }

    /**
     * @param JsonResponse $jsonResponse
     * @param array $data
     * @return array|ProfilingJsonResponse
     */
    public static function createFromPrevious(JsonResponse $jsonResponse, array $data) {
        $profiling = new ProfilingJsonResponse($jsonResponse->data, $jsonResponse->success, $jsonResponse->total, $jsonResponse->pages, $jsonResponse->message);
        $profiling->setProfile($data);
        return $profiling;
    }
}