<?php
    namespace PSFS\services;

    use PSFS\base\Logger;
    use PSFS\base\Service;
    use PSFS\base\Singleton;
    use PSFS\base\types\helpers\InjectorHelper;
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
         * @return array
         */
        public function getModules()
        {
            $modules = [];
            $domains = $this->route->getDomains();
            if (count($domains)) {
                foreach (array_keys($domains) as $domain) {
                    try {
                        if (!preg_match('/^\@ROOT/i', $domain)) {
                            $modules[] = str_replace('/', '', str_replace('@', '', $domain));
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
         * @param string $module
         *
         * @return array
         */
        public function extractApiEndpoints($module)
        {
            $module_path = CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Api";
            $endpoints = [];
            if (file_exists($module_path)) {
                $finder = new Finder();
                $finder->files()->depth('== 0')->in($module_path)->name('*.php');
                if (count($finder)) {
                    /** @var \SplFileInfo $file */
                    foreach ($finder as $file) {
                        $namespace = "\\{$module}\\Api\\" . str_replace('.php', '', $file->getFilename());
                        $endpoints[$namespace] = $this->extractApiInfo($namespace);
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
        public function extractApiInfo($namespace)
        {
            $info = [];
            $reflection = new \ReflectionClass($namespace);
            $publicMethods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            if (count($publicMethods)) {
                /** @var \ReflectionMethod $method */
                foreach ($publicMethods as $method) {
                    try {
                        $mInfo = $this->extractMethodInfo($namespace, $method, $reflection);
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
         * Extract method from doc comments
         *
         * @param string $comments
         *
         * @return string
         */
        protected function extractMethod($comments = '')
        {
            $method = 'GET';
            preg_match('/@(get|post|put|delete)\n/i', $comments, $method);

            return strtoupper($method[1]);
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
	            $properties = array_merge($properties, InjectorHelper::extractProperties($reflector, \ReflectionMethod::IS_PUBLIC));
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
            $modelDto  = [];
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
                            list($field, $dto) = explode('=', $subDto);
                            if (false !== strpos($dto, '[') && false !== strpos($dto, ']')) {
                                $dto = str_replace(']', '', str_replace('[', '', $dto));
                                $isArray = true;
                            }
                            $dto = $this->extractModelFields($dto);
                            $modelDto[$field] = ($isArray) ? [$dto] : $dto;
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
                    $fieldNames = $tableMap::getFieldNames();
                    if (count($fieldNames)) {
                        foreach ($fieldNames as $field) {
                            $variable = $reflector->getProperty(strtolower($field));
                            $varDoc = $variable->getDocComment();
                            $payload[$field] = $this->extractVarType($varDoc);
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
         *
         * @return array
         */
        protected function extractMethodInfo($namespace, \ReflectionMethod $method, \ReflectionClass $reflection)
        {
            $methodInfo = NULL;
            $docComments = $method->getDocComment();
            $shortName = $reflection->getShortName();
            $modelNamespace = str_replace('Api', 'Models', $namespace);
            if (FALSE !== $docComments && preg_match('/\@route\ /i', $docComments)) {
                $visibility = $this->extractVisibility($docComments);
                $route = str_replace('{__API__}', $shortName, $this->extractRoute($docComments));
                if ($visibility && preg_match('/^\/api\//i', $route)) {
                    try {
                        $methodInfo = [
                            'url'         => $route,
                            'method'      => $this->extractMethod($docComments),
                            'description' => str_replace('{__API__}', $shortName, $this->extractDescription($docComments)),
                            'return'      => $this->extractReturn($modelNamespace, $docComments),
                        ];
                        if (in_array($methodInfo['method'], ['POST', 'PUT'])) {
                            $methodInfo['payload'] = $this->extractPayload($modelNamespace, $docComments);
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
            switch(strtolower($format)) {
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
                case 'float':
                case 'double':
                    $swaggerType = 'integer';
                    $swaggerFormat = 'int32';
                    break;
                case 'date':
                    $swaggerType = 'string';
                    $swaggerFormat = 'date';
                    break;
                case 'datetime':
                    $swaggerType = 'string';
                    $swaggerFormat = 'date-time	';
                    break;

            }
            return [$swaggerType, $swaggerFormat];
        }

        /**
         * Method that parse the definitions for the api's
         * @param array $endpoint
         *
         * @return array
         */
        public static function extractSwaggerDefinition(array $endpoint)
        {
            $definitions = [];
            if (array_key_exists('definitions', $endpoint)) {
                foreach ($endpoint['definitions'] as $dtoName => $definition) {
                    $dto = [
                        "type" => "object",
                        "properties" => [],
                    ];
                    foreach ($definition as $field => $format) {
                        if (is_array($format)) {
                            $subDtoName = preg_replace('/Dto$/', '', $dtoName);
                            $subDtoName = preg_replace('/DtoList$/', '', $subDtoName);
                            $subDto = self::extractSwaggerDefinition(['definitions' => [
                                $subDtoName => $format,
                            ]]);
                            if (array_key_exists($subDtoName, $subDto)) {
                                $definitions = $subDto;
                            } else {
                                $definitions[$subDtoName] = $subDto;
                            }
                            $dto['properties'][$field] = [
                                '$ref' => "#/definitions/" . $subDtoName,
                            ];
                        } else {
                            list($type, $format) = self::translateSwaggerFormats($format);
                            $dto['properties'][$field] = [
                                "type" => $type,
                            ];
                            if (strlen($format)) {
                               $dto['properties'][$field]['format'] = $format;
                            }
                        }
                    }
                    $definitions[$dtoName] = $dto;
                }
            }
            return $definitions;
        }

        /**
         * Method that export
         * @param array $modules
         *
         * @return array
         */
        public static function swaggerFormatter(array $modules = [])
        {
            $endpoints = [];
            pre($modules, true);
            $dtos = [];
            $formatted = [
                "swagger" => "2.0",
                "host" => Router::getInstance()->getRoute(''),
                "basePath" => "/api",
                "schemes" => ["http", "https"],
                "externalDocs" => [
                    "description" => "Principal Url",
                    "url" => Router::getInstance()->getRoute(''),
                ]
            ];
            foreach($endpoints as $model) {
                foreach ($model as $endpoint) {
                    $dtos += self::extractSwaggerDefinition($endpoint);
                }
            }
            $formatted['definitions'] = $dtos;
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
