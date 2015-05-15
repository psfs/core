<?php

namespace PSFS\base\types;

use PSFS\base\types\interfaces\AuthInterface;
use PSFS\base\types\SecureTrait;

class SecureClass implements AuthInterface
{
    use SecureTrait;
}