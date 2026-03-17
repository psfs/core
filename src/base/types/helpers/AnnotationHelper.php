<?php

namespace PSFS\base\types\helpers;

/**
 * Class AnnotationHelper
 * @package PSFS\base\types\helper
 */
final class AnnotationHelper
{

    /**
     * @param string $comments
     * @return string|null
     */
    public static function extractRoute(string $comments = '', \ReflectionMethod $method = null): ?string
    {
        return MetadataReader::getTagValue('route', $comments, null, $method);
    }

    /**
     * Método que extrae la visibilidad de una ruta
     * @param string $docComments
     * @return bool
     */
    public static function extractReflectionVisibility(string $docComments, \ReflectionClass|\ReflectionMethod|\ReflectionProperty|null $reflector = null): bool
    {
        return (bool)MetadataReader::getTagValue('visible', $docComments, true, $reflector);
    }

    /**
     * Método que extrae el parámetro de caché
     * @param string $docComments
     * @return bool
     */
    public static function extractReflectionCacheability(string $docComments, \ReflectionMethod $method = null): bool
    {
        return (bool)MetadataReader::getTagValue('cache', $docComments, false, $method);
    }

    /**
     * Método que extrae la etiqueta a mostrar
     * @param string $docComments
     * @return string
     */
    public static function extractReflectionLabel(string $docComments, \ReflectionClass|\ReflectionMethod|\ReflectionProperty|null $reflector = null): ?string
    {
        return MetadataReader::getTagValue('label', $docComments, 'Undefined action', $reflector);
    }

    /**
     * Método que extrae el método http
     * @param string $docComments
     * @return string
     */
    public static function extractReflectionHttpMethod(string $docComments, \ReflectionMethod $method = null): string
    {
        return (string)MetadataReader::getTagValue('http', $docComments, 'ALL', $method);
    }

    /**
     * @param string $docComments
     * @return mixed|string
     */
    public static function extractDocIcon(string $docComments, \ReflectionMethod $method = null): mixed
    {
        return MetadataReader::getTagValue('icon', $docComments, '', $method);
    }

    /**
     * @param string $docComments
     * @return string|null
     */
    public static function extractApi(string $docComments, \ReflectionClass $class = null): ?string
    {
        return MetadataReader::getTagValue('api', $docComments, '', $class);
    }

    /**
     * Method that extract the instance of the class
     * @param string $docComments
     * @return string|null
     */
    public static function extractAction(string $docComments, \ReflectionMethod $method = null): ?string
    {
        return MetadataReader::getTagValue('action', $docComments, null, $method);
    }

    /**
     * @param string $needle
     * @param string $comments
     * @param string|null $default
     * @return string|null
     */
    public static function extractFromDoc(string $needle, string $comments, string $default = null, \ReflectionClass|\ReflectionMethod|\ReflectionProperty|null $reflector = null): mixed
    {
        return MetadataReader::getTagValue($needle, $comments, $default, $reflector);
    }
}
