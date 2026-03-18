<?php

namespace PSFS\base\types\interfaces;

interface AnnotationInterface
{
    /**
     * @return array
     */
    function getTags();

    /**
     * @return array
     */
    function parse();
}
