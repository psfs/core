<?php

namespace PSFS\base\types;

use PSFS\base\types\interfaces\AuthInterface;


class SecureClass implements AuthInterface
{
    use SecureTrait;
}
