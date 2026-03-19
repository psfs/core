<?php

namespace PSFS\base\types\traits\Api;

use PSFS\base\Request;
use PSFS\base\Router;
use ReflectionClass;

/**
 * @package PSFS\base\types\traits\Api
 */
trait PostmanFormaterTrait
{
    /**
     * @param array $module
     * @param array $endpoints
     * @return array
     */
    public function postmanFormatter(array $module, array $endpoints): array
    {
        $collection = [
            'info' => [
                '_postman_id' => uniqid('psfs-', true),
                'name' => t('Module API collection ') . $module['name'],
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                [
                    'key' => 'baseUrl',
                    'value' => '{{protocol}}://{{host}}/' . $module['name'] . '/api',
                ],
                [
                    'key' => 'protocol',
                    'value' => Request::getInstance()->getServer('HTTPS') === 'on' ? 'https' : 'http',
                ],
                [
                    'key' => 'host',
                    'value' => preg_replace(
                        '/^(http|https)\:\/\/(.*)\/$/i',
                        '$2',
                        Router::getInstance()->getRoute('', true)
                    ),
                ],
            ],
            'item' => [],
        ];

        foreach ($endpoints as $class => $model) {
            $folder = [
                'name' => (new ReflectionClass($class))->getShortName(),
                'item' => [],
            ];
            foreach ($model as $endpoint) {
                if (!$this->shouldIncludeSwaggerEndpoint($endpoint)) {
                    continue;
                }
                $folder['item'][] = $this->buildPostmanItem($module, $endpoint);
            }
            if (count($folder['item'])) {
                $collection['item'][] = $folder;
            }
        }

        return $collection;
    }

    private function buildPostmanItem(array $module, array $endpoint): array
    {
        $urlPath = preg_replace('/\/' . $module['name'] . '\/api/i', '', $endpoint['url']);
        $urlPath = str_replace(['{', '}'], [':', ''], $urlPath);
        $method = strtoupper($endpoint['method'] ?? Request::VERB_GET);
        $request = [
            'method' => $method,
            'header' => $this->buildPostmanHeaders($endpoint),
            'url' => [
                'raw' => '{{baseUrl}}' . $urlPath,
                'host' => ['{{baseUrl}}'],
                'path' => array_values(array_filter(explode('/', ltrim($urlPath, '/')))),
                'query' => $this->buildPostmanQueryParams($endpoint),
            ],
            'description' => (string)($endpoint['description'] ?? ''),
        ];

        $body = $this->buildPostmanBody($endpoint, $method);
        if (!empty($body)) {
            $request['body'] = $body;
        }

        return [
            'name' => ($endpoint['class'] ?? 'Api') . '::' . ($endpoint['description'] ?: $urlPath),
            'request' => $request,
            'response' => [],
        ];
    }

    private function buildPostmanHeaders(array $endpoint): array
    {
        $headers = [];
        foreach ($endpoint['headers'] ?? [] as $header) {
            $headers[] = [
                'key' => (string)($header['name'] ?? ''),
                'value' => (string)($header['default'] ?? ''),
                'type' => 'text',
                'disabled' => !($header['required'] ?? false),
            ];
        }
        return $headers;
    }

    private function buildPostmanQueryParams(array $endpoint): array
    {
        $query = [];
        foreach ($endpoint['query'] ?? [] as $param) {
            $query[] = [
                'key' => (string)($param['name'] ?? ''),
                'value' => (string)($param['default'] ?? ''),
                'description' => (string)($param['description'] ?? ''),
                'disabled' => !($param['required'] ?? false),
            ];
        }
        return $query;
    }

    private function buildPostmanBody(array $endpoint, string $method): array
    {
        if (!in_array($method, [Request::VERB_POST, Request::VERB_PUT], true)) {
            return [];
        }
        if (!array_key_exists('payload', $endpoint)) {
            return [];
        }
        $properties = $endpoint['payload']['properties'] ?? [];
        $payload = [];
        foreach ($properties as $field => $info) {
            $payload[$field] = $info['default'] ?? null;
        }

        return [
            'mode' => 'raw',
            'raw' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'options' => [
                'raw' => [
                    'language' => 'json',
                ],
            ],
        ];
    }
}

