<?php
namespace PSFS\tests\base\helpers;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\ServiceHelper;

/**
 * Service Helper tests cases
 */
class ServiceHelperTest extends TestCase {

    public function testParsingJsonData() {
        $testData = [
            'string' => 'test',
            'number' => 1,
            'array' => []
        ];
        $jsonTestData = json_encode($testData);
        $parsedJsonData = ServiceHelper::parseRawData(ServiceHelper::TYPE_JSON, $testData);
        $this->assertNotEmpty($parsedJsonData);
        $this->assertEquals($jsonTestData, $parsedJsonData);
    }

    public function testParsingHttpData() {
        $testData = [
            'string' => 'test',
            'number' => 1,
        ];
        $httpTestData = http_build_query($testData);
        $parsedHTTPData = ServiceHelper::parseRawData(ServiceHelper::TYPE_HTTP, $testData);
        $this->assertNotEmpty($parsedHTTPData);
        $this->assertEquals($httpTestData, $parsedHTTPData);
    }

    public function testParsingMultipartData() {
        $testData = [
            'string' => 'test',
            'number' => 1,
            'array' => []
        ];
        $parsedMultipartData = ServiceHelper::parseRawData(ServiceHelper::TYPE_MULTIPART, $testData);
        $this->assertNotEmpty($parsedMultipartData);
        $this->assertEquals($testData, $parsedMultipartData);
    }
}