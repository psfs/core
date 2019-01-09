<?php
namespace PSFS\base\types\helpers;

/**
 * Class DocumentorHelper
 * @package PSFS\base\types\helpers
 */
class DocumentorHelper {

    /**
     * @param array $params
     * @param string $variable
     * @return bool
     */
    private static function searchPayloadParam($params, $variable) {
        $exists = false;
        if(null !== $params && count($params)) {
            foreach($params as $param) {
                if($param['name'] === $variable) {
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
    public static function parsePayload($endpoint, $dtos, $paths, $url, $method)
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
        if(!self::searchPayloadParam($paths[$url][$method]['parameters'], $endpoint['payload']['type'])) {
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
     * @param $name
     * @param $endpoint
     * @param $object
     * @param $paths
     * @param $url
     * @param $method
     * @param $dtos
     * @param $isReturn
     * @return array
     */
    public static function parseObjects(&$paths, &$dtos, $name, $endpoint, $object, $url, $method, $isReturn = false)
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

            if($paths[$url][$method]['responses'][200]['schema']['properties']['data']['type'] === 'boolean') {
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
     * Translator from php types to swagger types
     * @param string $format
     *
     * @return array
     */
    public static function translateSwaggerFormats($format)
    {
        switch (strtolower(preg_replace('/\\\\/im', '', $format))) {
            case 'bool':
            case 'boolean':
                $swaggerType = 'boolean';
                $swaggerFormat = '';
                break;
            default:
            case 'string':
            case 'varchar':
                $swaggerType = 'string';
                $swaggerFormat = '';
                break;
            case 'binary':
            case 'varbinary':
                $swaggerType = 'string';
                $swaggerFormat = 'password';
                break;
            case 'int':
            case 'integer':
                $swaggerType = 'integer';
                $swaggerFormat = 'int32';
                break;
            case 'float':
            case 'double':
                $swaggerType = 'number';
                $swaggerFormat = strtolower($format);
                break;
            case 'date':
                $swaggerType = 'string';
                $swaggerFormat = 'date';
                break;
            case 'timestamp':
            case 'datetime':
                $swaggerType = 'string';
                $swaggerFormat = 'date-time';
                break;

        }
        return [$swaggerType, $swaggerFormat];
    }

    /**
     * Method that parse the definitions for the api's
     * @param string $name
     * @param array $fields
     *
     * @return array
     */
    public static function extractSwaggerDefinition($name, array $fields)
    {
        $definition = [
            $name => [
                "type" => "object",
                "properties" => [],
            ],
        ];
        foreach ($fields as $field => $info) {
            if(array_key_exists('type', $info) && in_array($info['type'], ['array', 'object'])) {
                $definition[$name]['properties'][$field] = $info;
            } elseif(array_key_exists('type', $info)) {
                list($type, $format) = self::translateSwaggerFormats($info['type']);
                $fieldData = [
                    "type" => $type,
                    "required" => $info['required'],
                ];
                if(array_key_exists('description', $info)) {
                    $fieldData['description'] = $info['description'];
                }
                if(array_key_exists('format', $info)) {
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