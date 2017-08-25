<?php
namespace PSFS\base\dto;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use PSFS\base\Logger;
use PSFS\base\Singleton;

/**
 * Class Dto
 * @package PSFS\base\dto
 */
class Dto extends Singleton
{

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
     * Hydrate object from array
     * @param array $object
     */
    public function fromArray(array $object = array())
    {
        if (count($object) > 0) {
            foreach ($object as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }
}