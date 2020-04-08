<?php

namespace PSFS\test\services;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\traits\BoostrapTrait;
use PSFS\services\GeneratorService;

/**
 * Class GeneratorServiceTest
 * @package PSFS\test\services
 */
class GeneratorServiceTest extends TestCase
{
    use BoostrapTrait;

    const MODULE_NAME = 'CLIENT';

    public static $filesToCheckWithoutSchema = [
        DIRECTORY_SEPARATOR . 'phpunit.xml.dist',
        DIRECTORY_SEPARATOR . 'autoload.php',
        DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . self::MODULE_NAME . 'Test.php',
        DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'index.html.twig',
        DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . self::MODULE_NAME . 'Service.php',
        DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . self::MODULE_NAME . 'Controller.php',
        DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . self::MODULE_NAME . 'BaseController.php',
        DIRECTORY_SEPARATOR . 'Public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js',
        DIRECTORY_SEPARATOR . 'Public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'styles.css',
        DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'schema.xml',
        DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'propel.yml',
        DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'config.php',
        DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Sql' . DIRECTORY_SEPARATOR . 'CLIENT.sql',
        DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Sql' . DIRECTORY_SEPARATOR . 'sqldb.map',
    ];

    public static $filesToCheckWithSchema = [
        // Simple model creation
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'Solo.php',
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . 'SoloBaseApi.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Solo.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'SoloQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'Solo.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'SoloQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Map' . DIRECTORY_SEPARATOR . 'SoloTableMap.php',
        // Package model creation and I18n
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Test.php',
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . 'TestBaseApi.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Test.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'TestQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'TestI18n.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'TestI18nQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'Test.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'TestQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'TestI18n.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'TestI18nQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Map' . DIRECTORY_SEPARATOR . 'TestTableMap.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Test' . DIRECTORY_SEPARATOR . 'Map' . DIRECTORY_SEPARATOR . 'TestI18nTableMap.php',
        // Package related model
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'Related.php',
        DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'base' . DIRECTORY_SEPARATOR . 'RelatedBaseApi.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'Related.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'RelatedQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'Related.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'Base' . DIRECTORY_SEPARATOR . 'RelatedQuery.php',
        DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'Related' . DIRECTORY_SEPARATOR . 'Map' . DIRECTORY_SEPARATOR . 'RelatedTableMap.php',
    ];

    /**
     * @before
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function prepareDocumentRoot()
    {
        GeneratorHelper::createRoot();
    }

    public function createNewModule(GeneratorService $generatorService)
    {
        $generatorService->createStructureModule(self::MODULE_NAME, true);
        $this->checkBasicStructure();
    }

    public function testCreateExistingModule()
    {
        $generatorService = GeneratorService::getInstance();
        $this->assertInstanceOf(GeneratorService::class, $generatorService, 'Error getting GeneratorService instance');
        $modulePath = CORE_DIR . DIRECTORY_SEPARATOR . self::MODULE_NAME;

        $this->createNewModule($generatorService);

        GeneratorHelper::copyr(
            dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'generator' . DIRECTORY_SEPARATOR . 'Config',
            $modulePath . DIRECTORY_SEPARATOR . 'Config'
        );
        require_once $modulePath . DIRECTORY_SEPARATOR . 'autoload.php';
        $generatorService->createStructureModule(self::MODULE_NAME, false);
        $this->checkBasicStructure();

        foreach (self::$filesToCheckWithSchema as $fileName) {
            $this->assertFileExists($modulePath . $fileName, $fileName . ' do not exists after generate module with schema');
        }
        GeneratorHelper::deleteDir($modulePath);
        $this->assertDirectoryNotExists($modulePath, 'Error trying to delete the module');
    }

    private function checkBasicStructure()
    {
        $modulePath = CORE_DIR . DIRECTORY_SEPARATOR . self::MODULE_NAME;
        $this->assertDirectoryExists($modulePath, 'Directory not created');
        foreach (self::$filesToCheckWithoutSchema as $fileName) {
            $this->assertFileExists($modulePath . $fileName, $fileName . ' do not exists after generate module from scratch');
        }
    }
}
