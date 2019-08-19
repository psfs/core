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

    public function testServiceBasics() {
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