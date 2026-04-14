<?php

namespace PSFS\base\types\traits\Api;

use Exception;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\ColumnMap;
use PSFS\base\Logger;
use PSFS\base\Router;
use PSFS\base\dto\Dto;
use PSFS\base\types\helpers\AnnotationHelper;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\helpers\DocumentorHelper;
use PSFS\base\types\helpers\InjectorHelper;
use ReflectionClass;
use ReflectionException;

/**
 * @package PSFS\base\types\traits\Api
 */
trait DocumentorHelperTrait
{
    /**
     * Contract: provided by SwaggerDtoComposerTrait.
     */
    abstract protected function checkDtoAttributes(array $dto, array $modelDto, string $dtoName): array;

    public static $nativeMethods = [
        'modelList', // Api list
        'get', // Api get
        'post', // Api post
        'put', // Api put
        'delete', // Api delete
    ];

    /**
     * @return array<int, string>
     */
    protected function getNativeMethods(): array
    {
        return self::$nativeMethods;
    }

    /**
     * @param array|string $namespace
     * @param true $isArray
     * @return array
     */
    public function processPayload(array|string $namespace, bool $isArray): array
    {
        if (false !== strpos($namespace, '[') && false !== strpos($namespace, ']')) {
            $namespace = str_replace(']', '', str_replace('[', '', $namespace));
            $isArray = true;
        }
        $payload = $this->extractModelFields($namespace);
        return array($isArray, $payload);
    }

    /**
     *
     * @param string|null $comments
     * @param ReflectionClass|null $reflector
     *
     * @return string
     */
    protected function extractApi(?string $comments = '', ?ReflectionClass $reflector = null): string
    {
        return (string)AnnotationHelper::extractApi((string)$comments, $reflector);
    }

    /**
     *
     * @param string $comments
     *
     * @return boolean
     */
    protected function checkDeprecated($comments = '')
    {
        return preg_match('/@deprecated\n/i', $comments) === 1;
    }

    /**
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
     * @param string $model
     * @param string $comments
     * @return array
     * @throws ReflectionException
     */
    protected function extractPayload($model, $comments = '')
    {
        $payload = [];
        preg_match('/@payload\ (.*)\n/i', $comments, $doc);
        $isArray = false;
        if (count($doc)) {
            $namespace = str_replace('{__API__}', $model, $doc[1]);
            list($isArray, $payload) = $this->processPayload($namespace, $isArray);
            $reflector = new ReflectionClass($namespace);
            $shortName = $reflector->getShortName();
        } else {
            $namespace = $model;
            $shortName = $model;
        }

        return [$namespace, $shortName, $payload, $isArray];
    }

    /**
     * @param string $class
     * @return array
     * @throws ReflectionException
     */
    protected function extractDtoProperties($class)
    {
        $properties = [];
        $reflector = new ReflectionClass($class);
        if ($reflector->isSubclassOf(Dto::class)) {
            $properties = array_merge($properties, InjectorHelper::extractVariables($reflector));
        }

        return $properties;
    }

    /**
     * @param string $model
     * @param string $comments
     * @return array
     * @throws ReflectionException
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
                        list($isArray, $dto) = $this->processPayload($dtoName, $isArray);
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
     * @param string $requestModule
     * @return array
     */
    public function getModules($requestModule)
    {
        $modules = [];
        $domains = Router::getInstance()->getDomains();
        if (count($domains)) {
            foreach ($domains as $module => $info) {
                try {
                    $module = preg_replace('/(@|\/)/', '', $module);
                    if ($module === $requestModule && !preg_match('/^ROOT/i', $module)) {
                        $modules = [
                            'name' => $module,
                            'path' => realpath(dirname($info['base'] . DIRECTORY_SEPARATOR . '..')),
                        ];
                    }
                } catch (Exception $e) {
                    $modules[] = $e->getMessage();
                }
            }
        }

        return $modules;
    }

    /**
     * @param string $namespace
     * @param $namespace
     * @return array
     */
    protected function extractModelFields($namespace)
    {
        $payload = [];
        try {
            $reflector = new ReflectionClass($namespace);
            // Checks if reflector is a subclass of propel ActiveRecords
            if (null !== $reflector && $reflector->isSubclassOf(ActiveRecordInterface::class)) {
                $tableMap = $namespace::TABLE_MAP;
                $tableMap = $tableMap::getTableMap();

                foreach ($tableMap->getColumns() as $field) {
                    list($type, $format) = DocumentorHelper::translateSwaggerFormats($field->getType());
                    $info = [
                        "type" => $type,
                        "required" => $field->isNotNull(),
                        'format' => $format,
                    ];
                    if (count($field->getValueSet())) {
                        $info['enum'] = array_values($field->getValueSet());
                    }
                    if (null !== $field->getDefaultValue()) {
                        $info['default'] = $field->getDefaultValue();
                    }
                    $payload[ApiHelper::getColumnMapName($field)] = $info;
                }
            } elseif (null !== $reflector && $reflector->isSubclassOf(Dto::class)) {
                $payload = $this->extractDtoProperties($namespace);
            }
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
        }

        return $payload;
    }
}
