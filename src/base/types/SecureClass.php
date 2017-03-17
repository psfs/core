<?php

namespace PSFS\base\types;

use PSFS\base\types\interfaces\AuthInterface;
use PSFS\base\types\traits\SecureTrait;

/**
 * Class SecureClass
 * @package PSFS\base\types
 */
class SecureClass implements AuthInterface
{
    use SecureTrait;
}
