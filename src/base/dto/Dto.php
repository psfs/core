<?php
namespace PSFS\base\dto;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\InjectorHelper;

/**
 * Class Dto
 * @package PSFS\base\dto
 */
class Dto extends Singleton
{

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
                    } else {
                        $dto[$property->getName()] = $property->getValue($this);
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
     * @param string $key
     * @param mixed $value
     */
    protected function parseDtoField(array $properties, $key, $value = null) {
        $type = 'string';
        $is_array = false;
        if(array_key_exists($key, $properties)) {
            $type = $properties[$key];
            if(preg_match('/(\[\]|Array)/i', $type)) {
                $type = preg_replace('/(\[\]|Array)/i', '', $type);
                $is_array = true;
            }
        }
        $reflector = (class_exists($type)) ? new \ReflectionClass($type) : null;
        if(null !== $reflector && $reflector->isSubclassOf(Dto::class)) {
            if($is_array) {
                $this->$key = [];
                foreach($value as $data) {
                    if(null !== $data) {
                        $dto = new $type(false);
                        $dto->fromArray($data);
                        array_push($this->$key, $dto);
                    }
                }
            } else {
                if(null !== $value) {
                    $this->$key = new $type(false);
                    $this->$key->fromArray($value);
                } elseif(Config::getParam('api.default.null', true)) {
                    $this->$key = null;
                }
            }
        } else {
            switch($type) {
                default:
                case 'string':
                    $this->$key = $value;
                    break;
                case 'integer':
                    $this->$key = (integer)$value;
                    break;
                case 'float':
                    $this->$key = (float)$value;
                    break;
                case 'boolean':
                case 'bool':
                    $this->$key = (bool)$value;
                    break;
            }
        }
    }

    /**
     * Hydrate object from array
     * @param array $object
     */
    public function fromArray(array $object = array())
    {
        if (count($object) > 0) {
            $reflector = new \ReflectionClass($this);
            $properties = InjectorHelper::extractProperties($reflector, \ReflectionProperty::IS_PUBLIC, InjectorHelper::VAR_PATTERN);
            unset($reflector);
            foreach ($object as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->parseDtoField($properties, $key, $value);
                }
            }
        }
    }
}