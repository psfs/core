<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\CurlService;

class CurlServiceDefaultsTest extends TestCase
{
    private function service(string $verb, string $url, array $params = []): CurlService
    {
        $service = (new \ReflectionClass(new class extends CurlService {
        }))->newInstanceWithoutConstructor();
        $service->setUrl($url);
        $service->setType($verb);
        if ([] !== $params) {
            $service->setParams($params);
        }
        (new \ReflectionMethod($service, 'setDefaults'))->invoke($service);
        return $service;
    }

    public function testGetParametersAreAppendedToTheUrl(): void
    {
        $service = $this->service('GET', 'https://example.test/items', ['page' => 2, 'q' => 'a b']);

        self::assertSame('https://example.test/items?page=2&q=a+b', $service->getUrl());
        self::assertSame('GET', $service->getOptions()[CURLOPT_CUSTOMREQUEST]);
    }

    public function testGetWithoutParametersKeepsTheUrl(): void
    {
        self::assertSame('https://example.test/items', $this->service('GET', 'https://example.test/items')->getUrl());
    }

    public function testBodyVerbsSendTheParametersAsPostFields(): void
    {
        foreach (['POST', 'PUT', 'PATCH'] as $verb) {
            $service = $this->service($verb, 'https://example.test/items', ['name' => 'psfs']);

            $options = $service->getOptions();
            self::assertSame($verb, $options[CURLOPT_CUSTOMREQUEST]);
            self::assertArrayHasKey(CURLOPT_POSTFIELDS, $options, $verb);
            self::assertStringContainsString('psfs', (string)(is_array($options[CURLOPT_POSTFIELDS]) ? json_encode($options[CURLOPT_POSTFIELDS]) : $options[CURLOPT_POSTFIELDS]), $verb);
        }
    }

    public function testDeleteDoesNotSendABody(): void
    {
        $options = $this->service('DELETE', 'https://example.test/items/1')->getOptions();

        self::assertSame('DELETE', $options[CURLOPT_CUSTOMREQUEST]);
        self::assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
    }
}
