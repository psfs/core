<?php

namespace PSFS\command\base;

use PSFS\base\types\helpers\GeneratorHelper;

class CreateRootCommand
{
    public static function execute()
    {
        return GeneratorHelper::createRoot();
    }
}

