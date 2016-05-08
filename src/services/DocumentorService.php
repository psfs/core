<?php
    namespace PSFS\services;

    use PSFS\base\Service;
    use Symfony\Component\Finder\Finder;

    /**
     * Class DocumentorService
     * @package PSFS\services
     */
    class DocumentorService extends Service
    {
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
                    $docComments = $method->getDocComment();
                    if (FALSE !== $docComments && preg_match('/\@route\ /i', $docComments)) {
                        $visibility = $this->extractVisibility($docComments);
                        $route = str_replace('{__API__}', $reflection->getShortName(), $this->extractRoute($docComments));
                        if ($visibility && preg_match('/^\/api\//i', $route)) {
                            $methodInfo = [
                                'url'         => $route,
                                'method'      => $this->extractMethod($docComments),
                                'description' => str_replace('{__API__}', $reflection->getShortName(), $this->extractDescription($docComments)),
                            ];
                            if (in_array($methodInfo['method'], ['POST', 'PUT'])) {
                                $methodInfo['payload'] = $this->extractPayload(str_replace('Api', 'Models', $namespace), $docComments);
                            }
                            $info[] = $methodInfo;
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
         * @param string $comments
         *
         * @return string
         */
        protected function extractVarType($comments = '')
        {
            $type = 'string';
            preg_match('/@var\ (.*)\n/i', $comments, $varType);
            if (count($varType)) {
                $type = str_replace(' ', '', $varType[1]);
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
                $reflector = new \ReflectionClass($namespace);
                if (null !== $reflector) {
                    $tableMap = $namespace::TABLE_MAP;
                    $fieldNames = $tableMap::getFieldNames();
                    if (count($fieldNames)) {
                        foreach($fieldNames as $field) {
                            $variable = $reflector->getProperty(strtolower($field));
                            $varDoc = $variable->getDocComment();
                            $payload[$field] = $this->extractVarType($varDoc);
                        }
                    }
                }
            }
            return $payload;
        }
    }