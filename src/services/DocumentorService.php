<?php
namespace PSFS\services;

use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Service;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\base\types\helpers\RouterHelper;
use Symfony\Component\Finder\Finder;

/**
 * Class DocumentorService
 * @package PSFS\services
 */
class DocumentorService extends Service
{
    const DTO_INTERFACE = '\\PSFS\\base\\dto\\Dto';
    const MODEL_INTERFACE = '\\Propel\\Runtime\\ActiveRecord\\ActiveRecordInterface';
    /**
     * @Inyectable
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
     *
     * @return array
     */
    public function extractApiInfo($namespace, $module)
    {
        $info = [];
        if (class_exists($namespace)) {
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
        }

        return $payload;
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
                $fieldNames = $tableMap::getFieldNames(TableMap::TYPE_FIELDNAME);
                if (count($fieldNames)) {
                    foreach ($fieldNames as $field) {
                        $variable = $reflector->getProperty(strtolower($field));
                        $varDoc = $variable->getDocComment();
                        $payload[$tableMap::translateFieldName($field, TableMap::TYPE_FIELDNAME, TableMap::TYPE_PHPNAME)] = $this->extractVarType($varDoc);
                    }
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
                    $methodInfo = [
                        'url' => array_pop($route),
                        'method' => $info['http'],
                        'description' => $info['label'],
                        'return' => $return,
                        'objects' => $return['objects'],
                    ];
                    unset($methodInfo['return']['objects']);
                    if (in_array($methodInfo['method'], ['POST', 'PUT'])) {
                        $methodInfo['payload'] = $this->extractPayload($modelNamespace, $docComments);
                    } elseif($method->getNumberOfParameters() > 0) {
                        $methodInfo['parameters'] = [];
                        foreach($method->getParameters() as $parameter) {
                            $parameterName = $parameter->getName();
                            $types = [];
                            preg_match_all('/\@param\ (.*)\ \$'.$parameterName.'$/im', $docComments, $types);
                            if(count($types) > 1) {
                                $methodInfo['parameters'][$parameterName] = $types[1][0];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    jpre($e->getMessage());
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
        foreach ($fields as $field => $format) {
            if (is_array($format)) {
                $subDtoName = preg_replace('/Dto$/', '', $field);
                $subDtoName = preg_replace('/DtoList$/', '', $subDtoName);
                $subDto = self::extractSwaggerDefinition($$subDtoName, ['definitions' => [
                    $subDtoName => $format,
                ]]);
                if (array_key_exists($subDtoName, $subDto)) {
                    $definitions = $subDto;
                } else {
                    $definitions[$subDtoName] = $subDto;
                }
                $definition[$name]['properties'][$field] = [
                    '$ref' => "#/definitions/" . $subDtoName,
                ];
            } else {
                list($type, $format) = self::translateSwaggerFormats($format);
                $dto['properties'][$field] = [
                    "type" => $type,
                ];
                $definition[$name]['properties'][$field] = [
                    "type" => $type,
                ];
                if (strlen($format)) {
                    $definition[$name]['properties'][$field]['format'] = $format;
                }
            }
        }
        return $definition;
    }

    /**
     * @return array
     */
    private static function swaggerResponses() {
        $codes = [200, 400, 404, 500];
        $responses = [];
        foreach($codes as $code) {
            switch($code) {
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
                if(!preg_match('/^\/(admin|api)\//i', $endpoint['url']) && strlen($endpoint['url'])) {
                    $url = preg_replace('/\/'.$module['name'].'\/api/i', '', $endpoint['url']);
                    $description = $endpoint['description'];
                    $method = strtolower($endpoint['method']);
                    $paths[$url][$method] = [
                        'summary' => $description,
                        'produces' => ['application/json'],
                        'consumes' => ['application/json'],
                        'responses' => self::swaggerResponses(),
                        'parameters' => [],
                    ];
                    if(array_key_exists('parameters', $endpoint)) {
                        foreach($endpoint['parameters'] as $parameter => $type) {
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
                    foreach($endpoint['objects'] as $name => $object) {
                        if(class_exists($name)) {
                            $class = GeneratorHelper::extractClassFromNamespace($name);
                            $classDefinition = [
                                'type' => 'object',
                                '$ref' => '#/definitions/' . $class,
                            ];
                            $paths[$url][$method]['responses'][200]['schema']['properties']['data'] = $classDefinition;
                            $dtos += self::extractSwaggerDefinition($class, $object);
                            if(!isset($paths[$url][$method]['tags']) || !in_array($class, $paths[$url][$method]['tags'])) {
                                $paths[$url][$method]['tags'][] = $class;
                            }
                            if(array_key_exists('payload', $endpoint)) {
                                $paths[$url][$method]['parameters'][] = [
                                    'in' => 'body',
                                    'name' => $class,
                                    'required' => true,
                                    'schema' => $classDefinition
                                ];
                            }
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
}
