<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\types\CurlService;
use PSFS\base\types\helpers\CompatHelper;

class CurlServiceTlsTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = (new \ReflectionProperty(Config::class, 'config'))->getValue(Config::getInstance());
        CompatHelper::reset();
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Config::class, 'config'))->setValue(Config::getInstance(), $this->configBackup);
        CompatHelper::reset();
    }

    private function setConfig(array $values, array $remove = []): void
    {
        $config = array_merge($this->configBackup, $values);
        foreach ($remove as $key) {
            unset($config[$key]);
        }
        (new \ReflectionProperty(Config::class, 'config'))->setValue(Config::getInstance(), $config);
    }

    /**
     * @return array the options the service would hand to curl
     */
    private function options(string $url, array $serviceOptions = []): array
    {
        $service = (new \ReflectionClass(new class extends CurlService {
        }))->newInstanceWithoutConstructor();
        $service->setUrl($url);
        $service->setType('GET');
        foreach ($serviceOptions as $option => $value) {
            $service->addOption($option, $value);
        }
        (new \ReflectionMethod($service, 'setDefaults'))->invoke($service);
        return $service->getOptions();
    }

    public function testCertificateAndHostAreVerifiedByDefault(): void
    {
        $this->setConfig([], ['curl.sslVerifyPeer', 'curl.sslVerifyHost']);

        $options = $this->options('https://example.test/api');

        self::assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testVerificationCanStillBeDisabledThroughTheConfiguration(): void
    {
        $this->setConfig(['curl.sslVerifyPeer' => false, 'curl.sslVerifyHost' => 0]);

        $options = $this->options('https://example.test/api');

        self::assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(0, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testAServiceCanDisableItThroughItsOwnOptions(): void
    {
        $this->setConfig([], ['curl.sslVerifyPeer', 'curl.sslVerifyHost']);

        $options = $this->options('https://example.test/api', [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]);

        self::assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(0, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testVerifyPeerAndVerifyHostAreNotMixedUp(): void
    {
        $this->setConfig([], ['curl.sslVerifyPeer', 'curl.sslVerifyHost']);

        // The value of VERIFYPEER used to be passed as the VERIFYHOST argument
        $options = $this->options('https://example.test/api', [CURLOPT_SSL_VERIFYPEER => false]);

        self::assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testPlainHttpCallsKeepWorkingWithTheSameOptions(): void
    {
        $this->setConfig([], ['curl.sslVerifyPeer', 'curl.sslVerifyHost']);

        $options = $this->options('http://example.test/api');

        self::assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }
}
