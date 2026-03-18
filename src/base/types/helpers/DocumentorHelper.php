<?php

namespace PSFS\base\types\helpers;

/**
 * @package PSFS\base\types\helpers
 */
class DocumentorHelper
{

    /**
     * @param array $params
     * @param string $variable
     * @return bool
     */
    private static function searchPayloadParam(array $params, string $variable): bool
    {
        $exists = false;
        if (null !== $params && count($params)) {
            foreach ($params as $param) {
                if ($param['name'] === $variable) {
                    $exists = true;
                    break;
                }
            }
        }
        return $exists;
    }

    /**
     * @param array $endpoint
     * @param array $dtos
     * @param array $paths
     * @param string $url
     * @param string $method
     * @return array
     */
    public static function parsePayload(array $endpoint, array $dtos, array $paths, string $url, string $method): array
    {
        $schema = [
            'type' => $endpoint['payload']['is_array'] ? 'array' : 'object',
        ];
        if ($endpoint['payload']['is_array']) {
            $schema['items'] = [
                '$ref' => '#/definitions/' . $endpoint['payload']['type'],
            ];
        } else {
            $schema['$ref'] = '#/definitions/' . $endpoint['payload']['type'];
        }
        if (!self::searchPayloadParam($paths[$url][$method]['parameters'], $endpoint['payload']['type'])) {
            $paths[$url][$method]['parameters'][] = [
                'in' => 'body',
                'name' => $endpoint['payload']['type'],
                'required' => true,
                'schema' => $schema,
            ];
        }
        return [$dtos, $paths];
    }

    /**
     * @param $paths
     * @param $dtos
     * @param $name
     * @param $endpoint
     * @param $object
     * @param $url
     * @param $method
     */
    public static function parseObjects(&$paths, &$dtos, $name, $endpoint, $object, $url, $method): void
    {
        if (class_exists($name)) {
            $class = GeneratorHelper::extractClassFromNamespace($name);
            if (array_key_exists('data', $endpoint['return']) && count(array_keys($object)) === count(array_keys($endpoint['return']['data']))) {
                $classDefinition = [
                    'type' => 'object',
                    '$ref' => '#/definitions/' . $class,
                ];
            } else {
                $classDefinition = [
                    'type' => 'array',
                    'items' => [
                        '$ref' => '#/definitions/' . $class,
                    ],
                ];
            }

            if ($paths[$url][$method]['responses'][200]['schema']['properties']['data']['type'] === 'boolean') {
                $paths[$url][$method]['responses'][200]['schema']['properties']['data'] = $classDefinition;
            }
            $dtos += self::extractSwaggerDefinition($class, $object);
            if (array_key_exists('payload', $endpoint)) {
                list($dtos, $paths) = DocumentorHelper::parsePayload($endpoint, $dtos, $paths, $url, $method);
            }
        }
        if (!isset($paths[$url][$method]['tags']) || !in_array($endpoint['class'], $paths[$url][$method]['tags'])) {
            $paths[$url][$method]['tags'][] = $endpoint['class'];
        }
    }

    /**
     * @param string $format
     *
     * @return array
     */
    public static function translateSwaggerFormats(string $format): array
    {
        $normalized = self::normalizeSwaggerFormat($format);
        $map = [
            'bool' => ['boolean', ''],
            'boolean' => ['boolean', ''],
            'string' => ['string', ''],
            'varchar' => ['string', ''],
            'binary' => ['string', 'password'],
            'varbinary' => ['string', 'password'],
            'int' => ['integer', 'int32'],
            'integer' => ['integer', 'int32'],
            'date' => ['string', 'date'],
            'timestamp' => ['string', 'date-time'],
            'datetime' => ['string', 'date-time'],
        ];
        if (array_key_exists($normalized, $map)) {
            return $map[$normalized];
        }
        if (in_array($normalized, ['float', 'double'], true)) {
            return ['number', $normalized];
        }
        return ['string', ''];
    }

    private static function normalizeSwaggerFormat(string $format): string
    {
        return strtolower((string)preg_replace('/\\\\/im', '', $format));
    }

    /**
     * @param string $name
     * @param array $fields
     *
     * @return array
     */
    public static function extractSwaggerDefinition(string $name, array $fields): array
    {
        $definition = [
            $name => [
                "type" => "object",
                "properties" => [],
            ],
        ];
        foreach ($fields as $field => $info) {
            if (array_key_exists('type', $info) && in_array($info['type'], ['array', 'object'])) {
                $definition[$name]['properties'][$field] = $info;
            } elseif (array_key_exists('type', $info)) {
                list($type, $format) = self::translateSwaggerFormats($info['type']);
                $fieldData = [
                    "type" => $type,
                    "required" => $info['required'],
                ];
                if (array_key_exists('description', $info)) {
                    $fieldData['description'] = $info['description'];
                }
                if (array_key_exists('format', $info)) {
                    $fieldData['format'] = $info['format'];
                }
                $dto['properties'][$field] = $fieldData;
                $definition[$name]['properties'][$field] = $fieldData;
                if (strlen($format)) {
                    $definition[$name]['properties'][$field]['format'] = $format;
                }
            }
        }
        return $definition;
    }
}
