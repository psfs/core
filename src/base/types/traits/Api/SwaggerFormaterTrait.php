<?php

namespace PSFS\base\types\traits\Api;

use Exception;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\DocumentorHelper;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\helpers\RouterHelper;
use PSFS\services\DocumentorService;
use ReflectionClass;
use ReflectionMethod;

/**
 * Trait SwaggerFormaterTrait
 * @package PSFS\base\types\traits\Api
 */
trait SwaggerFormaterTrait
{

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
     * Method that export
     * @param array $module
     * @param array $endpoints
     *
     * @return array
     */
    public function swaggerFormatter(array $module, array $endpoints)
    {
        $formatted = [
            "swagger" => "2.0",
            "host" => preg_replace('/^(http|https)\:\/\/(.*)\/$/i', '$2', Router::getInstance()->getRoute('', true)),
            "basePath" => '/' . $module['name'] . '/api',
            "schemes" => [Request::getInstance()->getServer('HTTPS') === 'on' ? 'https' : 'http'],
            "info" => [
                "title" => t('Documentación API módulo ') . $module['name'],
                "version" => Config::getParam('api.version', '1.0.0'),
                "contact" => [
                    "name" => Config::getParam("author", "Fran López"),
                    "email" => Config::getParam("author.email", "fran.lopez84@hotmail.es"),
                ]
            ]
        ];
        $dtos = $paths = [];
        foreach ($endpoints as $model) {
            foreach ($model as $endpoint) {
                if (!preg_match('/^\/(admin|api)\//i', $endpoint['url']) && strlen($endpoint['url'])) {
                    $url = preg_replace('/\/' . $module['name'] . '\/api/i', '', $endpoint['url']);
                    $description = $endpoint['description'];
                    $method = strtolower($endpoint['method']);
                    $paths[$url][$method] = [
                        'summary' => $description,
                        'produces' => ['application/json'],
                        'consumes' => ['application/json'],
                        'responses' => $this->swaggerResponses(),
                        'parameters' => [],
                    ];
                    if (array_key_exists('parameters', $endpoint)) {
                        foreach ($endpoint['parameters'] as $parameter => $type) {
                            list($type, $format) = DocumentorHelper::translateSwaggerFormats($type);
                            $paths[$url][$method]['parameters'][] = [
                                'in' => 'path',
                                'required' => true,
                                'name' => $parameter,
                                'type' => $type,
                                'format' => $format,
                            ];
                        }
                    }
                    if (array_key_exists('query', $endpoint)) {
                        foreach ($endpoint['query'] as $query) {
                            $paths[$url][$method]['parameters'][] = $query;
                        }
                    }
                    if (array_key_exists('headers', $endpoint)) {
                        foreach ($endpoint['headers'] as $query) {
                            $paths[$url][$method]['parameters'][] = $query;
                        }
                    }
                    foreach ($endpoint['objects'] as $name => $object) {
                        DocumentorHelper::parseObjects($paths, $dtos, $name, $endpoint, $object, $url, $method);
                    }
                }
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

    /**
     * @param $dto
     * @param $modelDto
     * @param $dtoName
     * @return array
     */
    protected function checkDtoAttributes($dto, $modelDto, $dtoName)
    {
        foreach ($dto as $param => &$info) {
            if (array_key_exists('class', $info)) {
                if ($info['is_array']) {
                    $modelDto['objects'][$dtoName][$param] = [
                        'type' => 'array',
                        'items' => [
                            '$ref' => '#/definitions/' . $info['type'],
                        ]
                    ];
                } else {
                    $modelDto['objects'][$dtoName][$param] = [
                        'type' => 'object',
                        '$ref' => '#/definitions/' . $info['type'],
                    ];
                }
                $modelDto['objects'][$info['class']] = $info['properties'];
                $paramDto = $this->checkDtoAttributes($info['properties'], $info['properties'], $info['class']);
                if (array_key_exists('objects', $paramDto)) {
                    $modelDto['objects'] = array_merge($modelDto['objects'], $paramDto['objects']);
                }
            } else {
                $modelDto['objects'][$dtoName][$param] = $info;
            }
        }
        return $modelDto;
    }

    /**
     * Method that extract all the needed info for each method in each API
     *
     * @param string $namespace
     * @param ReflectionMethod $method
     * @param ReflectionClass $reflection
     * @param string $module
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function extractMethodInfo($namespace, ReflectionMethod $method, ReflectionClass $reflection, $module)
    {
        $methodInfo = NULL;
        $docComments = $method->getDocComment();
        if (FALSE !== $docComments && preg_match('/\@route\ /i', $docComments)) {
            $api = $this->extractApi($reflection->getDocComment());
            list($route, $info) = RouterHelper::extractRouteInfo($method, $api, $module);
            $route = explode('#|#', $route);
            $modelNamespace = str_replace('Api', 'Models', $namespace);
            if ($info['visible'] && !$this->checkDeprecated($docComments)) {
                try {
                    $return = $this->extractReturn($modelNamespace, $docComments);
                    $url = array_pop($route);
                    $methodInfo = [
                        'url' => str_replace('/' . $module . '/api', '', $url),
                        'method' => $info['http'],
                        'description' => $info['label'],
                        'return' => $return,
                        'objects' => array_key_exists('objects', $return) ? $return['objects'] : [],
                        'class' => $reflection->getShortName(),
                    ];
                    unset($methodInfo['return']['objects']);
                    $this->setRequestParams($method, $methodInfo, $modelNamespace, $docComments);
                    $this->setQueryParams($method, $methodInfo);
                    $this->setRequestHeaders($reflection, $methodInfo);
                } catch (Exception $e) {
                    Logger::log($e->getMessage(), LOG_ERR);
                }
            }
        }

        return $methodInfo;
    }

    /**
     * @param ReflectionMethod $method
     * @param $methodInfo
     */
    protected function setQueryParams(ReflectionMethod $method, &$methodInfo)
    {
        if (in_array($methodInfo['method'], [Request::VERB_GET, Request::VERB_POST]) && in_array($method->getShortName(), self::$nativeMethods)) {
            $methodInfo['query'] = [];
            $methodInfo['query'][] = [
                "name" => "__limit",
                "in" => "query",
                "description" => t("Límite de registros a devolver, -1 para devolver todos los registros"),
                "required" => false,
                "type" => "integer",
            ];
            $methodInfo['query'][] = [
                "name" => "__page",
                "in" => "query",
                "description" => t("Página a devolver"),
                "required" => false,
                "type" => "integer",
            ];
            $methodInfo['query'][] = [
                "name" => "__fields",
                "in" => "query",
                "description" => t("Campos a devolver"),
                "required" => false,
                "type" => "array",
                "items" => [
                    "type" => "string",
                ]
            ];
        }
    }

    /**
     * @param ReflectionClass $reflection
     * @param $methodInfo
     */
    protected function setRequestHeaders(ReflectionClass $reflection, &$methodInfo)
    {

        $methodInfo['headers'] = [];
        foreach ($reflection->getProperties() as $property) {
            $doc = $property->getDocComment();
            preg_match('/@header\ (.*)\n/i', $doc, $headers);
            if (count($headers)) {
                $header = [
                    "name" => $headers[1],
                    "in" => "header",
                    "required" => true,
                ];

                // Extract var type
                $header['type'] = $this->extractVarType($doc);

                // Extract description
                $header['description'] = InjectorHelper::getLabel($doc);

                // Extract default value
                $header['default'] = InjectorHelper::getDefaultValue($doc);

                $methodInfo['headers'][] = $header;
            }
        }
    }

    /**
     * @param ReflectionMethod $method
     * @param array $methodInfo
     * @param string $modelNamespace
     * @param string $docComments
     */
    protected function setRequestParams(ReflectionMethod $method, &$methodInfo, $modelNamespace, $docComments)
    {
        if (in_array($methodInfo['method'], ['POST', 'PUT'])) {
            list($payloadNamespace, $payloadNamespaceShortName, $payloadDto, $isArray) = $this->extractPayload($modelNamespace, $docComments);
            if (count($payloadDto)) {
                $methodInfo['payload'] = [
                    'type' => $payloadNamespaceShortName,
                    'properties' => $payloadDto,
                    'is_array' => $isArray,
                ];
                $methodInfo = $this->checkDtoAttributes($payloadDto, $methodInfo, $payloadNamespace);
            }
        }
        if ($method->getNumberOfParameters() > 0) {
            $methodInfo['parameters'] = [];
            foreach ($method->getParameters() as $parameter) {
                $parameterName = $parameter->getName();
                $types = [];
                preg_match_all('/\@param\ (.*)\ \$' . $parameterName . '$/im', $docComments, $types);
                if (count($types) > 1 && count($types[1]) > 0) {
                    $methodInfo['parameters'][$parameterName] = $types[1][0];
                }
            }
        }
    }
}
