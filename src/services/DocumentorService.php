<?php

namespace PSFS\services;

use Propel\Runtime\Map\ColumnMap;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Service;
use PSFS\base\types\helpers\GeneratorHelper;
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

    private $classes = [];

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
                    if (!preg_match('/^ROOT/i', $module) && $module == $requestModule) {
                        $modules = [
                            'name' => $module,
                            'path' => realpath($info['template'] . DIRECTORY_SEPARATOR . '..'),
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
        $module_path = $module['path'] . DIRECTORY_SEPARATOR . 'Api';
        $module_name = $module['name'];
        $endpoints = [];
        if (file_exists($module_path)) {
            $finder = new Finder();
            $finder->files()->in($module_path)->depth(0)->name('*.php');
            if (count($finder)) {
                /** @var \SplFileInfo $file */
                foreach ($finder as $file) {
                    $namespace = "\\{$module_name}\\Api\\" . str_replace('.php', '', $file->getFilename());
                    $info = $this->extractApiInfo($namespace, $module_name);
                    if (!empty($info)) {
                        $endpoints[$namespace] = $info;
                    }
                }
            }
        }
        return $endpoints;
    }

    /**
     * Method that extract all the endpoit information by reflection
     *
     * @param string $namespace
     * @param string $module
     * @return array
     */
    public function extractApiInfo($namespace, $module)
    {
        $info = [];
        if (Router::exists($namespace) && !I18nHelper::checkI18Class($namespace)) {
            $reflection = new \ReflectionClass($namespace);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                try {
                    $mInfo = $this->extractMethodInfo($namespace, $method, $reflection, $module);
                    if (NULL !== $mInfo) {
                        $info[] = $mInfo;
                    }
                } catch (\Exception $e) {
                    Logger::getInstance()->errorLog($e->getMessage());
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
            $visible = !('false' == $visibility[1]);
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
                if (!preg_match('/(\*\*|\@)/i', $doc) && preg_match('/\*\ /i', $doc)) {
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
        if (count($doc)) {
            $namespace = str_replace('{__API__}', $model, $doc[1]);
            $payload = $this->extractModelFields($namespace);
            $reflector = new \ReflectionClass($namespace);
            $namespace = $reflector->getShortName();
        } else {
            $namespace = $model;
        }

        return [$namespace, $payload];
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
     * @return string
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
                        $isArray = false;
                        list($field, $dtoName) = explode('=', $subDto);
                        if (false !== strpos($dtoName, '[') && false !== strpos($dtoName, ']')) {
                            $dtoName = str_replace(']', '', str_replace('[', '', $dtoName));
                            $isArray = true;
                        }
                        $dto = $this->extractModelFields($dtoName);
                        $modelDto[$field] = ($isArray) ? [$dto] : $dto;
                        $modelDto['objects'][$dtoName] = $dto;
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
                    list($type, $format) = DocumentorService::translateSwaggerFormats($field->getType());
                    $info = [
                        "type" => $type,
                        "required" => $field->isNotNull(),
                        'format' => $format,
                    ];
                    $payload[$field->getPhpName()] = $info;
                }
            } elseif (null !== $reflector && $reflector->isSubclassOf(self::DTO_INTERFACE)) {
                $payload = $this->extractDtoProperties($namespace);
            }
        } catch (\Exception $e) {
            Logger::getInstance()->errorLog($e->getMessage());
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
            $api = self::extractApi($reflection->getDocComment());
            list($route, $info) = RouterHelper::extractRouteInfo($method, $api, $module);
            $route = explode('#|#', $route);
            $modelNamespace = str_replace('Api', 'Models', $namespace);
            if ($info['visible'] && !self::checkDeprecated($docComments)) {
                try {
                    $return = $this->extractReturn($modelNamespace, $docComments);
                    $url = array_pop($route);
                    $methodInfo = [
                        'url' => str_replace("/" . $module . "/api", '', $url),
                        'method' => $info['http'],
                        'description' => $info['label'],
                        'return' => $return,
                        'objects' => $return['objects'],
                        'class' => $reflection->getShortName(),
                    ];
                    unset($methodInfo['return']['objects']);
                    $this->setRequestParams($method, $methodInfo, $modelNamespace, $docComments);
                    $this->setQueryParams($method, $methodInfo);
                    $this->setRequestHeaders($reflection, $methodInfo);
                } catch (\Exception $e) {
                    Logger::getInstance()->errorLog($e->getMessage());
                }
            }
        }

        return $methodInfo;
    }

    /**
     * Translator from php types to swagger types
     * @param string $format
     *
     * @return array
     */
    public static function translateSwaggerFormats($format)
    {
        switch (strtolower($format)) {
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
                $swaggerFormat = 'binary';
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
            list($type, $format) = self::translateSwaggerFormats($info['type']);
            $dto['properties'][$field] = [
                "type" => $type,
                "required" => $info['required'],
            ];
            $definition[$name]['properties'][$field] = [
                "type" => $type,
                "required" => $info['required'],
            ];
            if (strlen($format)) {
                $definition[$name]['properties'][$field]['format'] = $format;
            }
        }
        return $definition;
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
                    $message = _('Successful response');
                    break;
                case 400:
                    $message = _('Client error in request');
                    break;
                case 404:
                    $message = _('Service not found');
                    break;
                case 500:
                    $message = _('Server error');
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
            "schemes" => [Request::getInstance()->getServer('HTTPS') == 'on' ? "https" : "http"],
            "info" => [
                "title" => _('Documentación API módulo ') . $module['name'],
                "version" => Config::getParam('api.version', '1.0'),
                "contact" => [
                    "name" => Config::getParam("author", "Fran López"),
                    "email" => Config::getParam("author_email", "fran.lopez84@hotmail.es"),
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
                            list($type, $format) = self::translateSwaggerFormats($type);
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
                        if (class_exists($name)) {
                            $class = GeneratorHelper::extractClassFromNamespace($name);
                            if(array_key_exists('data', $endpoint['return']) && count(array_keys($object)) === count(array_keys($endpoint['return']['data']))) {
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

                            $paths[$url][$method]['responses'][200]['schema']['properties']['data'] = $classDefinition;
                            $dtos += self::extractSwaggerDefinition($class, $object);
                            if (array_key_exists('payload', $endpoint)) {
                                $dtos[$endpoint['payload']['type']] = [
                                    'type' => 'object',
                                    'properties' => $endpoint['payload']['properties'],
                                ];
                                $paths[$url][$method]['parameters'][] = [
                                    'in' => 'body',
                                    'name' => $endpoint['payload']['type'],
                                    'required' => true,
                                    'schema' => [
                                        'type' => 'object',
                                        '$ref' => '#/definitions/' . $endpoint['payload']['type'],
                                    ],
                                ];
                            }
                        }
                        if (!isset($paths[$url][$method]['tags']) || !in_array($endpoint['class'], $paths[$url][$method]['tags'])) {
                            $paths[$url][$method]['tags'][] = $endpoint['class'];
                        }
                    }
                }
            }
        }
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
                "description" => _("Límite de registros a devolver, -1 para devolver todos los registros"),
                "required" => false,
                "type" => "integer",
            ];
            $methodInfo['query'][] = [
                "name" => "__page",
                "in" => "query",
                "description" => _("Página a devolver"),
                "required" => false,
                "type" => "integer",
            ];
            $methodInfo['query'][] = [
                "name" => "__fields",
                "in" => "query",
                "description" => _("Campos a devolver"),
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
                preg_match('/@label\ (.*)\n/i', $doc, $label);
                if(count($label)) {
                    $header['description'] = _($label[1]);
                }

                // Extract default value
                preg_match('/@default\ (.*)\n/i', $doc, $default);
                if(count($default)) {
                    $header['default'] = $default[1];
                }
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
            list($payloadNamespace, $payloadDto) = $this->extractPayload($modelNamespace, $docComments);
            if (count($payloadDto)) {
                $methodInfo['payload'] = [
                    'type' => $payloadNamespace,
                    'properties' => $payloadDto,
                ];
            }
        }
        if ($method->getNumberOfParameters() > 0) {
            $methodInfo['parameters'] = [];
            foreach ($method->getParameters() as $parameter) {
                $parameterName = $parameter->getName();
                $types = [];
                preg_match_all('/\@param\ (.*)\ \$' . $parameterName . '$/im', $docComments, $types);
                if (count($types) > 1) {
                    $methodInfo['parameters'][$parameterName] = $types[1][0];
                }
            }
        }
    }
}
