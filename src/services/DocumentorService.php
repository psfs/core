<?php

namespace PSFS\services;

use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Service;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\helpers\DocumentorHelper;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\helpers\RouterHelper;
use Symfony\Component\Finder\Finder;

/**
 * Class DocumentorService
 * @package PSFS\services
 */
class DocumentorService extends Service
{
    public static $nativeMethods = [
        'modelList', // Api list
        'get', // Api get
        'post', // Api post
        'put', // Api put
        'delete', // Api delete
    ];

    const DTO_INTERFACE = '\\PSFS\\base\\dto\\Dto';
    const MODEL_INTERFACE = '\\Propel\\Runtime\\ActiveRecord\\ActiveRecordInterface';

    /**
     * @Injectable
     * @var \PSFS\base\Router route
     */
    protected $route;


    /**
     * Method that extract all modules
     * @param string $requestModule
     * @return array
     */
    public function getModules($requestModule)
    {
        $modules = [];
        $domains = $this->route->getDomains();
        if (count($domains)) {
            foreach ($domains as $module => $info) {
                try {
                    $module = preg_replace('/(@|\/)/', '', $module);
                    if ($module === $requestModule && !preg_match('/^ROOT/i', $module)) {
                        $modules = [
                            'name' => $module,
                            'path' => dirname($info['base'] . DIRECTORY_SEPARATOR . '..'),
                        ];
                    }
                } catch (\Exception $e) {
                    $modules[] = $e->getMessage();
                }
            }
        }

        return $modules;
    }

    /**
     * Method that extract all endpoints for each module
     *
     * @param array $module
     *
     * @return array
     */
    public function extractApiEndpoints(array $module)
    {
        $modulePath = $module['path'] . DIRECTORY_SEPARATOR . 'Api';
        $moduleName = $module['name'];
        $endpoints = [];
        if (file_exists($modulePath)) {
            $finder = new Finder();
            $finder->files()->in($modulePath)->depth(0)->name('*.php');
            if (count($finder)) {
                /** @var \SplFileInfo $file */
                foreach ($finder as $file) {
                    $namespace = "\\{$moduleName}\\Api\\" . str_replace('.php', '', $file->getFilename());
                    $info = $this->extractApiInfo($namespace, $moduleName);
                    if (!empty($info)) {
                        $endpoints[$namespace] = $info;
                    }
                }
            }
        }
        return $endpoints;
    }

    /**
     * @param $namespace
     * @param $module
     * @return array
     * @throws \ReflectionException
     */
    public function extractApiInfo($namespace, $module)
    {
        $info = [];
        if (Router::exists($namespace) && !I18nHelper::checkI18Class($namespace)) {
            $reflection = new \ReflectionClass($namespace);
            $visible = InjectorHelper::checkIsVisible($reflection->getDocComment());
            if($visible) {
                foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    try {
                        $mInfo = $this->extractMethodInfo($namespace, $method, $reflection, $module);
                        if (NULL !== $mInfo) {
                            $info[] = $mInfo;
                        }
                    } catch (\Exception $e) {
                        Logger::log($e->getMessage(), LOG_ERR);
                    }
                }
            }
        }
        return $info;
    }

    /**
     * Extract route from doc comments
     *
     * @param string $comments
     *
     * @return string
     */
    protected function extractRoute($comments = '')
    {
        $route = '';
        preg_match('/@route\ (.*)\n/i', $comments, $route);

        return $route[1];
    }

    /**
     * Extract api from doc comments
     *
     * @param string $comments
     *
     * @return string
     */
    protected function extractApi($comments = '')
    {
        $api = '';
        preg_match('/@api\ (.*)\n/i', $comments, $api);

        return $api[1];
    }

    /**
     * Extract api from doc comments
     *
     * @param string $comments
     *
     * @return boolean
     */
    protected function checkDeprecated($comments = '')
    {
        return false != preg_match('/@deprecated\n/i', $comments);
    }

    /**
     * Extract visibility from doc comments
     *
     * @param string $comments
     *
     * @return boolean
     */
    protected function extractVisibility($comments = '')
    {
        $visible = TRUE;
        preg_match('/@visible\ (true|false)\n/i', $comments, $visibility);
        if (count($visibility)) {
            $visible = !('false' === $visibility[1]);
        }

        return $visible;
    }

    /**
     * Method that extract the description for the endpoint
     *
     * @param string $comments
     *
     * @return string
     */
    protected function extractDescription($comments = '')
    {
        $description = '';
        $docs = explode("\n", $comments);
        if (count($docs)) {
            foreach ($docs as &$doc) {
                if (!preg_match('/(\*\*|\@)/', $doc) && preg_match('/\*\ /', $doc)) {
                    $doc = explode('* ', $doc);
                    $description = $doc[1];
                }
            }
        }

        return $description;
    }

    /**
     * Method that extract the type of a variable
     *
     * @param string $comments
     *
     * @return string
     */
    public static function extractVarType($comments = '')
    {
        $type = 'string';
        preg_match('/@var\ (.*) (.*)\n/i', $comments, $varType);
        if (count($varType)) {
            $aux = trim($varType[1]);
            $type = str_replace(' ', '', strlen($aux) > 0 ? $varType[1] : $varType[2]);
        }

        return $type;
    }

    /**
     * Method that extract the payload for the endpoint
     *
     * @param string $model
     * @param string $comments
     *
     * @return array
     */
    protected function extractPayload($model, $comments = '')
    {
        $payload = [];
        preg_match('/@payload\ (.*)\n/i', $comments, $doc);
        $isArray = false;
        if (count($doc)) {
            $namespace = str_replace('{__API__}', $model, $doc[1]);
            if (false !== strpos($namespace, '[') && false !== strpos($namespace, ']')) {
                $namespace = str_replace(']', '', str_replace('[', '', $namespace));
                $isArray = true;
            }
            $payload = $this->extractModelFields($namespace);
            $reflector = new \ReflectionClass($namespace);
            $shortName = $reflector->getShortName();
        } else {
            $namespace = $model;
            $shortName = $model;
        }

        return [$namespace, $shortName, $payload, $isArray];
    }

    /**
     * Extract all the properties from Dto class
     *
     * @param string $class
     *
     * @return array
     */
    protected function extractDtoProperties($class)
    {
        $properties = [];
        $reflector = new \ReflectionClass($class);
        if ($reflector->isSubclassOf(self::DTO_INTERFACE)) {
            $properties = array_merge($properties, InjectorHelper::extractVariables($reflector));
        }

        return $properties;
    }

    /**
     * Extract return class for api endpoint
     *
     * @param string $model
     * @param string $comments
     *
     * @return array
     */
    protected function extractReturn($model, $comments = '')
    {
        $modelDto = [];
        preg_match('/\@return\ (.*)\((.*)\)\n/i', $comments, $returnTypes);
        if (count($returnTypes)) {
            // Extract principal DTO information
            if (array_key_exists(1, $returnTypes)) {
                $modelDto = $this->extractDtoProperties($returnTypes[1]);
            }
            if (array_key_exists(2, $returnTypes)) {
                $subDtos = preg_split('/,?\ /', str_replace('{__API__}', $model, $returnTypes[2]));
                if (count($subDtos)) {
                    foreach ($subDtos as $subDto) {
                        list($field, $dtoName) = explode('=', $subDto);
                        $isArray = false;
                        if (false !== strpos($dtoName, '[') && false !== strpos($dtoName, ']')) {
                            $dtoName = str_replace(']', '', str_replace('[', '', $dtoName));
                            $isArray = true;
                        }
                        $dto = $this->extractModelFields($dtoName);
                        $modelDto[$field] = $isArray ? [$dto] : $dto;
                        $modelDto['objects'][$dtoName] = $dto;
                        $modelDto = $this->checkDtoAttributes($dto, $modelDto, $dtoName);
                    }
                }
            }
        }

        return $modelDto;
    }

    /**
     * Extract all fields from a ActiveResource model
     *
     * @param string $namespace
     *
     * @return mixed
     */
    protected function extractModelFields($namespace)
    {
        $payload = [];
        try {
            $reflector = new \ReflectionClass($namespace);
            // Checks if reflector is a subclass of propel ActiveRecords
            if (NULL !== $reflector && $reflector->isSubclassOf(self::MODEL_INTERFACE)) {
                $tableMap = $namespace::TABLE_MAP;
                $tableMap = $tableMap::getTableMap();
                /** @var ColumnMap $field */
                foreach ($tableMap->getColumns() as $field) {
                    list($type, $format) = DocumentorHelper::translateSwaggerFormats($field->getType());
                    $info = [
                        "type" => $type,
                        "required" => $field->isNotNull(),
                        'format' => $format,
                    ];
                    if(count($field->getValueSet())) {
                        $info['enum'] = array_values($field->getValueSet());
                    }
                    if(null !== $field->getDefaultValue()) {
                        $info['default'] = $field->getDefaultValue();
                    }
                    $payload[ApiHelper::getColumnMapName($field)] = $info;
                }
            } elseif (null !== $reflector && $reflector->isSubclassOf(self::DTO_INTERFACE)) {
                $payload = $this->extractDtoProperties($namespace);
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
        }

        return $payload;
    }

    /**
     * Method that extract all the needed info for each method in each API
     *
     * @param string $namespace
     * @param \ReflectionMethod $method
     * @param \ReflectionClass $reflection
     * @param string $module
     *
     * @return array
     */
    protected function extractMethodInfo($namespace, \ReflectionMethod $method, \ReflectionClass $reflection, $module)
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
                } catch (\Exception $e) {
                    Logger::log($e->getMessage(), LOG_ERR);
                }
            }
        }

        return $methodInfo;
    }

    /**
     * @return array
     */
    private static function swaggerResponses()
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
     *
     * @return array
     */
    public static function swaggerFormatter(array $module)
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
        $endpoints = DocumentorService::getInstance()->extractApiEndpoints($module);
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
                        'responses' => self::swaggerResponses(),
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
                    $isReturn = true;
                    foreach ($endpoint['objects'] as $name => $object) {
                        DocumentorHelper::parseObjects($paths, $dtos, $name, $endpoint, $object, $url, $method, $isReturn);
                        $isReturn = false;
                    }
                }
            }
        }
        ksort($dtos);
        uasort($paths, function($path1, $path2) {
            $key1 = array_keys($path1)[0];
            $key2 = array_keys($path2)[0];
            return strcmp($path1[$key1]['tags'][0], $path2[$key2]['tags'][0]);
        });
        $formatted['definitions'] = $dtos;
        $formatted['paths'] = $paths;
        return $formatted;
    }

    /**
     * Method that extract the Dto class for the api documentation
     * @param string $dto
     * @param boolean $isArray
     *
     * @return string
     */
    protected function extractDtoName($dto, $isArray = false)
    {
        $dto = explode('\\', $dto);
        $modelDto = array_pop($dto) . "Dto";
        if ($isArray) {
            $modelDto .= "List";
        }

        return $modelDto;
    }

    /**
     * @param \ReflectionMethod $method
     * @param $methodInfo
     */
    protected function setQueryParams(\ReflectionMethod $method, &$methodInfo)
    {
        if (in_array($methodInfo['method'], ['GET']) && in_array($method->getShortName(), self::$nativeMethods)) {
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
     * @param \ReflectionClass $reflection
     * @param $methodInfo
     */
    protected function setRequestHeaders(\ReflectionClass $reflection, &$methodInfo)
    {

        $methodInfo['headers'] = [];
        foreach($reflection->getProperties() as $property) {
            $doc = $property->getDocComment();
            preg_match('/@header\ (.*)\n/i', $doc, $headers);
            if(count($headers)) {
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
     * @param \ReflectionMethod $method
     * @param array $methodInfo
     * @param string $modelNamespace
     * @param string $docComments
     */
    protected function setRequestParams(\ReflectionMethod $method, &$methodInfo, $modelNamespace, $docComments)
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
                if(array_key_exists('objects', $paramDto)) {
                    $modelDto['objects'] = array_merge($modelDto['objects'], $paramDto['objects']);
                }
            } else {
                $modelDto['objects'][$dtoName][$param] = $info;
            }
        }
        return $modelDto;
    }
}
