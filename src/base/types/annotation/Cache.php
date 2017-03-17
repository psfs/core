<?php
namespace PSFS\base\types\annotation;

use PSFS\base\types\interfaces\AnnotationInterface;
use PSFS\base\types\traits\SingletonTrait;

/**
 * Class Cache
 * @package PSFS\base\types\annotation
 */
class Cache implements AnnotationInterface
{
    use SingletonTrait;

    /**
     * @inheritDoc
     */
    function getTags()
    {
        // TODO: Implement getTags() method.
        return ['cache'];
    }

    /**
     * @inheritDoc
     */
    function parse()
    {
        // TODO: Implement parse() method.
        return [];
    }
}