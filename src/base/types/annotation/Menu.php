<?php
namespace PSFS\base\types\annotation;

use PSFS\base\types\interfaces\AnnotationInterface;

class Menu implements AnnotationInterface {
    /**
     * @inheritDoc
     */
    function getTags()
    {
        // TODO: Implement getTags() method.
        return ['Menu', 'Name', 'Title'];
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