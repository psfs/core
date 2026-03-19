<?php

namespace PSFS\base\types\traits\Api;

use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\types\helpers\DocumentorHelper;

/**
 * @package PSFS\base\types\traits\Api
 */
trait SwaggerFormaterTrait
{
    use SwaggerDtoComposerTrait;
    use ApiEndpointExtractorTrait;
    use PostmanFormaterTrait;

    /**
     * @return array
     */
    protected function swaggerResponses()
    {
        $codes = [200, 400, 404, 500];
        $responses = [];
        foreach ($codes as $code) {
            switch ($code) {
                default:
                case 200:
                    $message = t('Successful response');
                    break;
                case 400:
                    $message = t('Client error in request');
                    break;
                case 404:
                    $message = t('Service not found');
                    break;
                case 500:
                    $message = t('Server error');
                    break;
            }
            $responses[$code] = [
                'description' => $message,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'success' => [
                            'type' => 'boolean'
                        ],
                        'message' => [
                            'type' => 'string'
                        ],
                        'data' => [
                            'type' => 'boolean',
                        ],
                        'total' => [
                            'type' => 'integer',
                            'format' => 'int32',
                        ],
                        'pages' => [
                            'type' => 'integer',
                            'format' => 'int32',
                        ]
                    ]
                ]
            ];
        }
        return $responses;
    }

    /**
     * @param array $module
     * @param array $endpoints
     *
     * @return array
     */
    public function swaggerFormatter(array $module, array $endpoints)
    {
        $formatted = $this->buildSwaggerDocumentSkeleton($module);
        $dtos = $paths = [];
        foreach ($endpoints as $model) {
            foreach ($model as $endpoint) {
                $this->appendEndpointToSwagger($module, $endpoint, $paths, $dtos);
            }
        }
        ksort($dtos);
        uasort($paths, function ($path1, $path2) {
            $key1 = array_keys($path1)[0];
            $key2 = array_keys($path2)[0];
            return strcmp($path1[$key1]['tags'][0], $path2[$key2]['tags'][0]);
        });
        $formatted['definitions'] = $dtos;
        $formatted['paths'] = $paths;
        return $formatted;
    }

    private function buildSwaggerDocumentSkeleton(array $module): array
    {
        return [
            "swagger" => "2.0",
            "host" => preg_replace('/^(http|https)\:\/\/(.*)\/$/i', '$2', Router::getInstance()->getRoute('', true)),
            "basePath" => '/' . $module['name'] . '/api',
            "schemes" => [Request::getInstance()->getServer('HTTPS') === 'on' ? 'https' : 'http'],
            "info" => [
                "title" => t('Module API documentation ') . $module['name'],
                "version" => Config::getParam('api.version', '1.0.0'),
                "contact" => [
                    "name" => Config::getParam("author", "Fran Lopez"),
                    "email" => Config::getParam("author.email", "fran.lopez84@hotmail.es"),
                ]
            ]
        ];
    }

    private function appendEndpointToSwagger(array $module, array $endpoint, array &$paths, array &$dtos): void
    {
        if (!$this->shouldIncludeSwaggerEndpoint($endpoint)) {
            return;
        }
        $url = preg_replace('/\/' . $module['name'] . '\/api/i', '', $endpoint['url']);
        $method = strtolower($endpoint['method']);
        $paths[$url][$method] = [
            'summary' => $endpoint['description'],
            'produces' => ['application/json'],
            'consumes' => ['application/json'],
            'responses' => $this->swaggerResponses(),
            'parameters' => [],
        ];
        $this->appendPathParameters($paths[$url][$method]['parameters'], $endpoint);
        $this->appendDirectParameters($paths[$url][$method]['parameters'], $endpoint, 'query');
        $this->appendDirectParameters($paths[$url][$method]['parameters'], $endpoint, 'headers');
        $objects = $endpoint['objects'] ?? [];
        foreach ($objects as $name => $object) {
            DocumentorHelper::parseObjects($paths, $dtos, $name, $endpoint, $object, $url, $method);
        }
    }

    private function shouldIncludeSwaggerEndpoint(array $endpoint): bool
    {
        return !preg_match('/^\/(admin|api)\//i', $endpoint['url']) && strlen($endpoint['url']) > 0;
    }

    private function appendPathParameters(array &$parameters, array $endpoint): void
    {
        if (!array_key_exists('parameters', $endpoint)) {
            return;
        }
        foreach ($endpoint['parameters'] as $parameter => $type) {
            list($type, $format) = DocumentorHelper::translateSwaggerFormats($type);
            $parameters[] = [
                'in' => 'path',
                'required' => true,
                'name' => $parameter,
                'type' => $type,
                'format' => $format,
            ];
        }
    }

    private function appendDirectParameters(array &$parameters, array $endpoint, string $section): void
    {
        if (!array_key_exists($section, $endpoint)) {
            return;
        }
        foreach ($endpoint[$section] as $query) {
            $parameters[] = $query;
        }
    }
}
