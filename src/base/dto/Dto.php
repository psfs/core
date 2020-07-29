<?php
namespace PSFS\base\dto;

use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\InjectorHelper;

/**
 * Class Dto
 * @package PSFS\base\dto
 */
class Dto extends Singleton implements \JsonSerializable
{
    /**
     * @var array
     */
    protected $__cache = [];
    public function __construct($hydrate = true)
    {
        parent::__construct();
        if($hydrate) {
            $this->fromArray(Request::getInstance()->getData());
        }
    }

    /**
     * ToArray wrapper
     * @return array
     */
    public function toArray()
    {
        return $this->__toArray();
    }

    /**
     * Convert dto to array representation
     * @return array
     */
    public function __toArray()
    {
        $dto = array();
        try {
            $reflectionClass = new \ReflectionClass($this);
            $properties = $reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC);
            if (count($properties) > 0) {
                /** @var \ReflectionProperty $property */
                foreach ($properties as $property) {
                    $value = $property->getValue($this);
                    if(is_object($value) && method_exists($value, 'toArray')) {
                        $dto[$property->getName()] = $value->toArray();
                    } elseif(is_array($value)) {
                        foreach($value as &$arrValue) {
                            if($arrValue instanceof Dto) {
                                $arrValue = $arrValue->toArray();
                            }
                        }
                        $dto[$property->getName()] = $value;
                    } else {
                        $type = InjectorHelper::extractVarType($property->getDocComment());
                        $dto[$property->getName()] = $this->checkCastedValue($property->getValue($this), $type);
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::log(get_class($this) . ': ' . $e->getMessage(), LOG_ERR);
        }
        return $dto;
    }

    /**
     * Convert to string representation
     * @return string
     */
    public function __toString()
    {
        return get_class($this);
    }

    /**
     * @param array $properties
     * @param $key
     * @param mixed $value
     * @throws \ReflectionException
     */
    protected function parseDtoField(array $properties, $key, $value = null) {
        list($type, $isArray) = $this->extractTypes($properties, $key);
        $reflector = (class_exists($type)) ? new \ReflectionClass($type) : null;
        if(null !== $reflector && $reflector->isSubclassOf(Dto::class)) {
            if(null !== $value && is_array($value)) {
                if(!array_key_exists($type, $this->__cache)) {
                    $this->__cache[$type] = new $type(false);
                }
                if($isArray) {
                    $this->$key = [];
                    foreach($value as $data) {
                        if(null !== $data && is_array($data)) {
                            $dto = clone $this->__cache[$type];
                            $dto->fromArray($data);
                            array_push($this->$key, $dto);
                        }
                    }
                } else {
                    $this->$key = clone $this->__cache[$type];
                    $this->$key->fromArray($value);
                }
            }
        } else {
            $this->castValue($key, $value, $type);
        }
    }

    /**
     * Hydrate object from array
     * @param array $object
     * @throws \ReflectionException
     */
    public function fromArray(array $object = array())
    {
        if (count($object) > 0) {
            $reflector = new \ReflectionClass($this);
            $properties = InjectorHelper::extractProperties($reflector, \ReflectionProperty::IS_PUBLIC, InjectorHelper::VAR_PATTERN);
            unset($reflector);
            foreach ($object as $key => $value) {
                if (property_exists($this, $key) && null !== $value) {
                    $this->parseDtoField($properties, $key, $value);
                }
            }
        }
    }

    /**
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @param array $properties
     * @param $key
     * @return array
     */
    protected function extractTypes(array $properties, $key)
    {
        $type = 'string';
        $isArray = false;
        if (array_key_exists($key, $properties)) {
            $type = $properties[$key];
            if (preg_match('/(\[\]|Array)/i', $type)) {
                $type = preg_replace('/(\[\]|Array)/i', '', $type);
                $isArray = true;
            }
        }
        return array($type, $isArray);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param string $type
     */
    protected function castValue($key, $value, $type)
    {
        $this->$key = $this->checkCastedValue($value, $type);
    }

    /**
     * @param mixed $rawValue
     * @param string $type
     * @return mixed
     */
    protected function checkCastedValue($rawValue, $type) {
        if(null !== $rawValue) {
            switch ($type) {
                default:
                case 'string':
                    $value = $rawValue;
                    break;
                case 'integer':
                case 'int':
                    $value = (integer)$rawValue;
                    break;
                case 'float':
                case 'double':
                    $value = (float)$rawValue;
                    break;
                case 'boolean':
                case 'bool':
                    $value = (bool)$rawValue;
                    break;
            }
        } else {
            $value = $rawValue;
        }
        return $value;
    }
}
