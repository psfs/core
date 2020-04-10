<?php
namespace PSFS\test\services;

use PSFS\base\Router;
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

    /**
     * @before
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function prepareDocumentRoot()
    {
        if(file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json')) {
            unlink(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json');
        }
        $this->assertFileNotExists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'domains.json', 'Previous generated domains json, please delete it before testing');
        GeneratorHelper::deleteDir(CACHE_DIR);
        $this->assertDirectoryNotExists(CACHE_DIR, 'Cache folder already exists with data');
        GeneratorHelper::createRoot(WEB_DIR, null, true);
    }

    /**
     * @param string $modulePath
     */
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

        Router::getInstance()->init();
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
