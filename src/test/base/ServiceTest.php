<?php
namespace PSFS\test\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Service;
use PSFS\base\Singleton;
use PSFS\test\examples\AuthServiceTest;

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
        self::assertInstanceOf(Service::class, $srv, '$srv is not a Service class');
        // Check Singleton
        self::assertInstanceOf(Singleton::class, $srv, '$srv is not a Singleton class');
        // Check initialization
        self::assertEmpty($srv->getUrl(), 'Service has previous url set');
        $srv->setUrl('www.example.com');
        self::assertNotEmpty($srv->getUrl(), 'Service has empty url');
        $srv->setUrl('www.google.com');
        self::assertNotEquals('www.example.com', $srv->getUrl(), 'Service does not update url');
        return $srv;
    }

    protected function checkParams(Service $srv) {
        $paramName = uniqid('test');
        $paramValue = microtime(true);
        // CHeck when param didn't exists
        $param = $srv->getParam($paramName);
        self::assertNull($param, 'Param exists before test');
        // Set params with bulk method
        $srv->setParams([$paramName => $paramValue]);
        self::assertNotEmpty($srv->getParams(), 'Service params are empty');
        $param = $srv->getParam($paramName);
        self::assertNotNull($param, 'Param did not exists');
        // Check to remove params
        $count = count($srv->getParams());
        $srv->dropParam($paramName);
        self::assertNotEquals(count($srv->getParams()), $count, 'Param not dropped');
        // Check adding one param
        $srv->addParam($paramName, $paramValue);
        $param = $srv->getParam($paramName);
        self::assertNotNull($param, 'Param did not exists');
        self::assertEquals($paramValue, $param, 'Different param value');
    }

    protected function checkOptions(Service $srv) {
        // Clean data
        $srv->setOptions([]);
        // CHeck when option didn't exists
        $option = $srv->getOption(CURLOPT_CONNECTTIMEOUT);
        self::assertNull($option, 'Option exists before test');
        // Set option with bulk method
        $srv->setOptions([CURLOPT_CONNECTTIMEOUT => 30]);
        self::assertNotEmpty($srv->getOptions(), 'Service options are empty');
        $option = $srv->getOption(CURLOPT_CONNECTTIMEOUT);
        self::assertNotNull($option, 'Option did not exists');
        // Check to remove options
        $count = count($srv->getOptions());
        $srv->dropOption(CURLOPT_CONNECTTIMEOUT);
        self::assertNotEquals(count($srv->getOptions()), $count, 'Option not dropped');
        // Check adding one option
        $srv->addOption(CURLOPT_CONNECTTIMEOUT, 30);
        $option = $srv->getOption(CURLOPT_CONNECTTIMEOUT);
        self::assertNotNull($option, 'Option did not exists');
        self::assertEquals(30, $option, 'Different option value');
    }

    public function testServiceTraits() {
        $srv = $this->getServiceInstance();
        self::assertInstanceOf(Service::class, $srv, '$srv is not a Service class');
        $this->checkParams($srv);
        $this->checkOptions($srv);

        // Initialize url, with default second param, the service has to clean all variables
        $srv->setUrl('https://example.com');

        // Tests has to be passed again
        $this->checkParams($srv);

        // Initialize service without cleaning params and options
        $srv->setUrl('https://google.com', false);
        self::assertNotEquals('https://example.com', $srv->getUrl(), 'Service does not update url');
        self::assertEquals('https://google.com', $srv->getUrl(), 'Service does not update url');
        self::assertNotEmpty($srv->getParams(), 'Params are empty');
        self::assertNotEmpty($srv->getOptions(), 'Options are empty');

    }

    public function testSimpleCall() {
        if($this->hasInternet()) {
            $this->markTestIncomplete('Pending make tests');
        } else {
            self::assertTrue(true, 'Not connected to internet');
        }
    }

    public function testAuthorizedCall() {
        $authSrv = AuthServiceTest::getInstance();
        // Generate random user and password
        $user = uniqid('user');
        $password = sha1(microtime(true));
        $basicAuth = "{$user}:{$password}";
        // Apply auth to a example service
        $authSrv->test($user, $password);
        // Check options created into curl resource
        $curl = $authSrv->getCon();
        self::assertTrue(is_resource($curl), 'Curl resource was not created');
        $callInfo = $authSrv->getCallInfo();
        $jsonResponse = $authSrv->getResult();
        // Get specific curl options that have to be set
        $authType = $authSrv->getOption(CURLOPT_HTTPAUTH);
        self::assertNotNull($authType, 'Auth not set');
        self::assertEquals(CURLAUTH_BASIC, $authType, 'Auth basic not set');
        $authString = $authSrv->getOption(CURLOPT_USERPWD);
        self::assertNotNull($authString, 'Basic auth string not set');
        self::assertEquals($basicAuth, $authString, 'Different auth string');
        // Check request response
        self::assertIsArray($jsonResponse, 'Bad json decoding');
        self::assertArrayHasKey('request_header', $callInfo, 'Verbose mode not activated');
        if(array_key_exists('request_header', $callInfo)) {
            $rawHeaders = explode("\r\n", $callInfo['request_header']);
            $headers = [];
            foreach($rawHeaders as $rawHeader) {
                $data = explode(": ", $rawHeader, 2);
                if(count($data) === 2) {
                    $headers[$data[0]] = $data[1];
                }
            }
            self::assertArrayHasKey('Authorization', $headers, 'Auth header not set');
            self::assertArrayHasKey('X-PSFS-SEC-TOKEN', $headers, 'PSFS Security header not set');
            if(array_key_exists('Authorization', $headers)) {
                $authorization = base64_decode(str_replace('Basic ', '', $headers['Authorization']));
                self::assertEquals($basicAuth, $authorization, 'Basic header different than expected');
            }
        }
    }

}
