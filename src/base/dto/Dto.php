<?php

namespace PSFS\base\dto;

use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\InjectorHelper;

/**
 * @package PSFS\base\dto
 */
class Dto extends Singleton implements \JsonSerializable
{
    use ValidatableDtoTrait;

    /**
     * @var array
     */
    protected array $__cache = [];

    public function __construct($hydrate = true)
    {
        parent::__construct();
        if ($hydrate) {
            $this->fromArray(Request::getInstance()->getData());
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->__toArray();
    }

    /**
     * @return array
     */
    public function __toArray()
    {
        $dto = [];
        try {
            $reflectionClass = new \ReflectionClass($this);
            $properties = $reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC);
            if (!empty($properties)) {
                foreach ($properties as $property) {
                    $value = $property->getValue($this);
                    if (is_object($value) && method_exists($value, 'toArray')) {
                        $dto[$property->getName()] = $value->toArray();
                    } elseif (is_array($value)) {
                        foreach ($value as &$arrValue) {
                            if ($arrValue instanceof Dto) {
                                $arrValue = $arrValue->toArray();
                            }
                        }
                        $dto[$property->getName()] = $value;
                    } else {
                        $type = InjectorHelper::extractVarType((string)$property->getDocComment(), $property);
                        $dto[$property->getName()] = $this->checkCastedValue(
                            $property->getValue($this),
                            $type ?: 'string'
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::log(get_class($this) . ': ' . $e->getMessage(), LOG_ERR);
        }
        return $dto;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return get_class($this);
    }

    /**
     * @param array $properties
     * @param string $key
     * @param mixed|null $value
     */
    protected function parseDtoField(array $properties, string $key, $value = null)
    {
        list($type, $isArray) = $this->extractTypes($properties, $key);
        $reflector = (class_exists($type)) ? new \ReflectionClass($type) : null;
        if (null !== $reflector && $reflector->isSubclassOf(Dto::class)) {
            if (is_array($value)) {
                if (!array_key_exists($type, $this->__cache)) {
                    $this->__cache[$type] = new $type(false);
                }
                if ($isArray) {
                    $this->$key = [];
                    foreach ($value as $data) {
                        if (is_array($data)) {
                            $dto = clone $this->__cache[$type];
                            $dto->fromArray($data);
                            $this->$key[] = $dto;
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
     * @param array $object
     * @throws \ReflectionException
     */
    public function fromArray(array $object = [])
    {
        $this->setValidationInputData($object);
        if (!empty($object)) {
            $reflector = new \ReflectionClass($this);
            $properties = $this->extractPublicPropertyTypes($reflector);
            unset($reflector);
            foreach ($object as $key => $value) {
                if (property_exists($this, $key) && null !== $value) {
                    $this->parseDtoField($properties, $key, $value);
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function extractPublicPropertyTypes(\ReflectionClass $reflector): array
    {
        $types = [];
        foreach ($reflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $type = InjectorHelper::extractVarType((string)$property->getDocComment(), $property);
            if (is_string($type) && trim($type) !== '') {
                $types[$property->getName()] = $type;
            }
        }
        return $types;
    }

    /**
     * @return mixed
     */
    public function jsonSerialize(): array|string|null
    {
        return $this->toArray();
    }

    /**
     * @param array $properties
     * @param string $key
     * @return array
     */
    protected function extractTypes(array $properties, string $key): array
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
    protected function castValue(string $key, mixed $value, string $type): void
    {
        $this->$key = $this->checkCastedValue($value, $type);
    }

    /**
     * @param mixed $rawValue
     * @param string $type
     * @return bool|float|int|string|null
     */
    protected function checkCastedValue(mixed $rawValue, string $type)
    {
        if (null === $rawValue) {
            return null;
        }
        $normalizedType = strtolower($type);
        if (in_array($normalizedType, ['integer', 'int'], true)) {
            return (int)$rawValue;
        }
        if (in_array($normalizedType, ['float', 'double'], true)) {
            return (float)$rawValue;
        }
        if (in_array($normalizedType, ['boolean', 'bool'], true)) {
            return (bool)$rawValue;
        }
        return $this->sanitizeStringLikeValue($rawValue);
    }

    private function sanitizeStringLikeValue(mixed $rawValue): mixed
    {
        if (is_array($rawValue)) {
            foreach ($rawValue as &$item) {
                $item = $this->sanitizeStringLikeValue($item);
            }
            return $rawValue;
        }
        if (is_string($rawValue)) {
            return I18nHelper::cleanHtmlAttacks($rawValue);
        }
        return $rawValue;
    }
}
