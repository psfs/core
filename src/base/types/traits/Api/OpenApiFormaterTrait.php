<?php

namespace PSFS\base\types\traits\Api;

use PSFS\base\config\Config;
use PSFS\base\Router;

/**
 * @package PSFS\base\types\traits\Api
 */
trait OpenApiFormaterTrait
{
    /**
     * Build an OpenAPI 3.1 document from extracted endpoint metadata.
     *
     * @param array $module
     * @param array $endpoints
     * @return array
     */
    public function openApiFormatter(array $module, array $endpoints): array
    {
        // Reuse the legacy formatter to keep response semantics and DTO inference aligned.
        $swagger = $this->swaggerFormatter($module, $endpoints);
        $openapi = [
            'openapi' => '3.1.0',
            'info' => $swagger['info'] ?? [
                'title' => t('Module API documentation ') . $module['name'],
                'version' => Config::getParam('api.version', '1.0.0'),
            ],
            'servers' => [
                [
                    'url' => '{{scheme}}://' . ($swagger['host'] ?? $this->resolveHost()) . ($swagger['basePath'] ?? ''),
                ],
            ],
            'components' => [
                'schemas' => [],
            ],
            'paths' => [],
        ];

        if (!empty($swagger['definitions']) && is_array($swagger['definitions'])) {
            $openapi['components']['schemas'] = $swagger['definitions'];
            ksort($openapi['components']['schemas']);
        }

        $paths = $swagger['paths'] ?? [];
        foreach ($paths as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                $normalized = [
                    'summary' => $operation['summary'] ?? '',
                    'tags' => $operation['tags'] ?? [],
                    'responses' => $this->normalizeOpenApiResponses($operation['responses'] ?? []),
                ];

                [$parameters, $requestBody] = $this->normalizeOpenApiParameters($operation['parameters'] ?? []);
                if (!empty($parameters)) {
                    $normalized['parameters'] = $parameters;
                }
                if (!empty($requestBody)) {
                    $normalized['requestBody'] = $requestBody;
                }

                $openapi['paths'][$path][strtolower((string)$method)] = $normalized;
            }
            ksort($openapi['paths'][$path]);
        }
        ksort($openapi['paths']);

        return $openapi;
    }

    /**
     * @param array $responses
     * @return array
     */
    private function normalizeOpenApiResponses(array $responses): array
    {
        $normalized = [];
        foreach ($responses as $status => $response) {
            $entry = [
                'description' => (string)($response['description'] ?? ''),
            ];
            if (isset($response['schema'])) {
                $entry['content'] = [
                    'application/json' => [
                        'schema' => $response['schema'],
                    ],
                ];
            }
            $normalized[(string)$status] = $entry;
        }
        ksort($normalized);
        return $normalized;
    }

    /**
     * @param array $parameters
     * @return array{0: array, 1: array}
     */
    private function normalizeOpenApiParameters(array $parameters): array
    {
        $normalized = [];
        $requestBody = [];
        foreach ($parameters as $parameter) {
            $in = $parameter['in'] ?? '';
            if ($in === 'body') {
                $requestBody = [
                    'required' => (bool)($parameter['required'] ?? false),
                    'content' => [
                        'application/json' => [
                            'schema' => $parameter['schema'] ?? ['type' => 'object'],
                        ],
                    ],
                ];
                continue;
            }

            $schema = [];
            if (isset($parameter['schema']) && is_array($parameter['schema'])) {
                $schema = $parameter['schema'];
            } else {
                $schema['type'] = $parameter['type'] ?? 'string';
                if (!empty($parameter['format'])) {
                    $schema['format'] = $parameter['format'];
                }
                if (isset($parameter['items'])) {
                    $schema['items'] = $parameter['items'];
                }
            }

            $normalized[] = [
                'name' => (string)($parameter['name'] ?? ''),
                'in' => (string)$in,
                'required' => (bool)($parameter['required'] ?? false),
                'description' => (string)($parameter['description'] ?? ''),
                'schema' => $schema,
            ];
        }

        usort(
            $normalized,
            static function (array $param1, array $param2): int {
                $left = ($param1['in'] ?? '') . ':' . ($param1['name'] ?? '');
                $right = ($param2['in'] ?? '') . ':' . ($param2['name'] ?? '');
                return strcmp($left, $right);
            }
        );

        return [$normalized, $requestBody];
    }

    /**
     * @return string
     */
    private function resolveHost(): string
    {
        return preg_replace('/^(http|https)\:\/\/(.*)\/$/i', '$2', Router::getInstance()->getRoute('', true));
    }
}
