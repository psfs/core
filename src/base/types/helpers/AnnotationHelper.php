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
    public static function extractRoute(string $comments = ''): ?string
    {
        return self::extractFromDoc('route', $comments);
    }

    /**
     * Método que extrae la visibilidad de una ruta
     * @param string $docComments
     * @return bool
     */
    public static function extractReflectionVisibility(string $docComments): bool
    {
        $visible = self::extractFromDoc('visible', $docComments, '');
        return !str_contains($visible, 'false');
    }

    /**
     * Método que extrae el parámetro de caché
     * @param string $docComments
     * @return bool
     */
    public static function extractReflectionCacheability(string $docComments): bool
    {
        return (bool)self::extractFromDoc('cache', $docComments, false);
    }

    /**
     * Método que extrae la etiqueta a mostrar
     * @param string $docComments
     * @return string
     */
    public static function extractReflectionLabel(string $docComments): ?string
    {
        return self::extractFromDoc('label', $docComments, 'Undefined action');
    }

    /**
     * Método que extrae el método http
     * @param string $docComments
     * @return string
     */
    public static function extractReflectionHttpMethod(string $docComments): string
    {
        preg_match('/@(GET|POST|PUT|DELETE)(\n|\r)/i', $docComments, $routeMethod);
        return (count($routeMethod) > 0) ? $routeMethod[1] : 'ALL';
    }

    /**
     * @param string $docComments
     * @return mixed|string
     */
    public static function extractDocIcon(string $docComments): mixed
    {
        return self::extractFromDoc('icon', $docComments, '');
    }

    /**
     * @param string $docComments
     * @return string|null
     */
    public static function extractApi(string $docComments): ?string
    {
        return self::extractFromDoc('api', $docComments, '');
    }

    /**
     * Method that extract the instance of the class
     * @param string $docComments
     * @return string|null
     */
    public static function extractAction(string $docComments): ?string
    {
        return self::extractFromDoc('action', $docComments);
    }

    /**
     * @param string $needle
     * @param string $comments
     * @param string|null $default
     * @return string|null
     */
    public static function extractFromDoc(string $needle, string $comments, string $default = null): ?string
    {
        preg_match('/@' . $needle . '\ (.*)(\n|\r)/im', $comments, $matches);
        return (count($matches) > 0) ? $matches[1] : $default;
    }
}
