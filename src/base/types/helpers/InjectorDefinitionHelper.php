<?php

namespace PSFS\base\types\helpers;

use PSFS\base\Router;
use ReflectionClass;
use ReflectionException;

class InjectorDefinitionHelper
{
    /**
     * @param mixed $cachedDefinition
     * @return array{isInjectable:bool,class:?string,singleton:bool,required:bool,source:?string}
     */
    public static function normalizeCachedDefinition(mixed $cachedDefinition): array
    {
        if (is_string($cachedDefinition) && trim($cachedDefinition) !== '') {
            return [
                'isInjectable' => true,
                'class' => trim($cachedDefinition),
                'singleton' => true,
                'required' => true,
                'source' => 'cache',
            ];
        }

        if (is_array($cachedDefinition) && is_string($cachedDefinition['class'] ?? null)) {
            return [
                'isInjectable' => true,
                'class' => trim((string)$cachedDefinition['class']),
                'singleton' => array_key_exists('singleton', $cachedDefinition)
                    ? (bool)$cachedDefinition['singleton']
                    : true,
                'required' => array_key_exists('required', $cachedDefinition)
                    ? (bool)$cachedDefinition['required']
                    : true,
                'source' => 'cache',
            ];
        }

        return [
            'isInjectable' => false,
            'class' => null,
            'singleton' => true,
            'required' => true,
            'source' => null,
        ];
    }

    /**
     * @param string $instanceType
     * @param bool $required
     * @param mixed $label
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    public static function buildVariableDefinition(string $instanceType, bool $required, mixed $label): array
    {
        [$normalizedType, $isArray] = self::normalizeInstanceType($instanceType);
        if ($normalizedType === '\\DateTime' || !Router::exists($normalizedType)) {
            list($type, $format) = DocumentorHelper::translateSwaggerFormats($normalizedType);
            return [
                'type' => $type,
                'format' => $format,
                'required' => $required,
                'description' => $label,
            ];
        }

        $instance = new ReflectionClass($normalizedType);
        return [
            'is_array' => $isArray,
            'class' => $normalizedType,
            'type' => $instance->getShortName(),
            'properties' => InjectorHelper::extractVariables($instance),
        ];
    }

    /**
     * @return array{string,bool}
     */
    private static function normalizeInstanceType(string $instanceType): array
    {
        $isArray = (bool)preg_match('/\[\]$/', $instanceType);
        if ($isArray) {
            $instanceType = str_replace('[]', '', $instanceType);
        }
        return [$instanceType, $isArray];
    }
}
