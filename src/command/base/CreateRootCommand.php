<?php
namespace PSFS\Command\base;

use PSFS\controller\GeneratorController;

class CreateRootCommand {
    public static function execute() {
        return GeneratorController::createRoot();
    }
}

