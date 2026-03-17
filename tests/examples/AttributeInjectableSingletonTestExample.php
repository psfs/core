<?php

namespace PSFS\tests\examples;

use PSFS\base\Singleton;
use PSFS\base\types\helpers\attributes\Injectable;

class AttributeInjectableSingletonTestExample extends Singleton
{
    /**
     * @var \PSFS\base\Security
     */
    #[Injectable]
    protected $security;
}
