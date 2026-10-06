<?php

namespace PSFS\base\types\traits\Generator;

use PSFS\base\exception\GeneratorException;
use PSFS\base\types\Api;
use ReflectionClass;

/** Validates custom API class declarations used by module generation. */
trait GeneratorNamespaceTrait
{
    /** @param string $namespace */
    public static function extractClassFromNamespace($namespace): string
    {
        $parts = preg_split('/(\\\\|\\/)/', $namespace);
        return array_pop($parts);
    }

    /**
     * @param string $namespace
     * @throws GeneratorException
     */
    public static function checkCustomNamespaceApi($namespace): void
    {
        if (!empty($namespace)) {
            if (class_exists($namespace)) {
                $reflector = new ReflectionClass($namespace);
                if (!$reflector->isSubclassOf(Api::class)) {
                    throw new GeneratorException(t('The defined class must extend PSFS\\\base\\\types\\\Api'), 501);
                } elseif (!$reflector->isAbstract()) {
                    throw new GeneratorException(t('The defined class must be abstract'), 501);
                }
            } else {
                throw new GeneratorException(t('The defined class for extending API does not exist'), 501);
            }
        }
    }
}
