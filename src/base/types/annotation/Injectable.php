<?php
namespace PSFS\base\types\annotation;

use PSFS\base\types\interfaces\AnnotationInterface;
use PSFS\base\types\traits\SingletonTrait;

/**
 * Class Injectable
 * @package PSFS\base\annotation
 */
class Injectable implements AnnotationInterface
{
    use SingletonTrait;

    /**
     * @inheritDoc
     */
    function getTags()
    {
        // TODO: Implement getTags() method.
        return ['Inyectable', 'Injectable', 'Autoload', 'Autowired'];
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