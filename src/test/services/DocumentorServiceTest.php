<?php
namespace PSFS\test\services;

use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\services\DocumentorService;

/**
 * Class DocumentorServiceTest
 * @package PSFS\test\services
 */
class DocumentorServiceTest extends GeneratorServiceTest{

    public static $namespaces = [
        '\CLIENT\Api\Test\Test',
        '\CLIENT\Api\Related\Related',
        '\CLIENT\Api\Solo',
    ];

    protected function clearContext($modulePath) {
        GeneratorHelper::deleteDir($modulePath);
        $this->assertDirectoryNotExists($modulePath, 'Error trying to delete the module');
    }

    /**
     * @throws \PSFS\base\exception\GeneratorException
     * @throws \ReflectionException
     */
    public function testApiDocumentation() {
        $modulePath = $this->checkCreateExistingModule();
        $this->assertDirectoryExists($modulePath, 'Module did not exists');

        $documentorService = DocumentorService::getInstance();
        $module = $documentorService->getModules(self::MODULE_NAME);
        $doc = $documentorService->extractApiEndpoints($module);
        foreach($doc as $namespace => $endpoints) {
            $this->assertContains($namespace, self::$namespaces, 'Namespace not included in the test');
            $this->assertCount(7, $endpoints, 'Different number of endpoints');
        }
        $this->clearContext($modulePath);
    }
}
