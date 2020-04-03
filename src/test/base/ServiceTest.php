<?php
namespace PSFS\Test;

use PHPUnit\Framework\TestCase;
use PSFS\base\Service;
use PSFS\base\Singleton;

/**
 * Class ServiceTest
 * @package PSFS\Test
 */
class ServiceTest extends TestCase {

    private function hasInternet() {
        // use 80 for http or 443 for https protocol
        $connected = @fsockopen("https://github.com", 443);
        if ($connected){
            fclose($connected);
            return true;
        }
        return false;
    }

    protected function getServiceInstance() {
        $srv = Service::getInstance();
        // Check instance
        $this->assertInstanceOf(Service::class, $srv, '$srv is not a Service class');
        // Check Singleton
        $this->assertInstanceOf(Singleton::class, $srv, '$srv is not a Singleton class');
        // Check initialization
        $this->assertEmpty($srv->getUrl(), 'Service has previous url set');
        $srv->setUrl('www.example.com');
        $this->assertNotEmpty($srv->getUrl(), 'Service has empty url');
        $srv->setUrl('www.google.com');
        $this->assertNotEquals('www.example.com', $srv->getUrl(), 'Service does not update url');
        return $srv;
    }

    protected function checkParams(Service $srv) {
        $this->assertEmpty($srv->getParams(), 'Service has params');
        $srv->addParam('test', true);
        $this->assertNotEmpty($srv->getParams(), 'Service params are empty');
    }

    protected function checkOptions(Service $srv) {
        $srv->addOption(CURLOPT_CONNECTTIMEOUT, 30);
        $this->assertNotEmpty($srv->getParams(), 'Service options are empty');
    }

    public function testServiceTraits() {
        $srv = $this->getServiceInstance();
        $this->assertInstanceOf(Service::class, $srv, '$srv is not a Service class');
        $this->checkParams($srv);
        $this->checkOptions($srv);

        // Initialize url, with default second param, the service has to clean all variables
        $srv->setUrl('https://example.com');

        // Tests has to be passed again
        $this->checkParams($srv);
        $this->checkOptions($srv);

        // Initialize service without cleaning params and options
        $srv->setUrl('https://google.com', false);
        $this->assertNotEquals('https://example.com', $srv->getUrl(), 'Service does not update url');
        $this->assertEquals('https://google.com', $srv->getUrl(), 'Service does not update url');
        $this->assertNotEmpty($srv->getParams(), 'Params are empty');
        $this->assertNotEmpty($srv->getOptions(), 'Options are empty');

    }

    public function testSimpleCall() {
        if($this->hasInternet()) {
            $this->markTestIncomplete('Pending make tests');
        } else {
            $this->assertTrue(true, 'Not connected to internet');
        }
    }

    public function testAuthorizedCall() {
        if($this->hasInternet()) {
            $this->markTestIncomplete('Pending make tests');
        } else {
            $this->assertTrue(true, 'Not connected to internet');
        }
    }

    public function testContentTypesCalls() {
        if($this->hasInternet()) {
            $this->markTestIncomplete('Pending make tests');
        } else {
            $this->assertTrue(true, 'Not connected to internet');
        }
    }
}
