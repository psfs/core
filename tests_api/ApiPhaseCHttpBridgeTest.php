<?php

namespace PSFS\apitests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PSFS\apitests\support\ClientModuleHarness;

#[Group('api')]
#[Group('api-phase-c-http')]
#[Group('api-mysql')]
class ApiPhaseCHttpBridgeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClientModuleHarness::acquire();
    }

    public static function tearDownAfterClass(): void
    {
        ClientModuleHarness::release();
    }

    protected function setUp(): void
    {
        ClientModuleHarness::resetSeedData();
    }

    public function testApiListEndpointReturnsPersistedData(): void
    {
        $response = ClientModuleHarness::dispatch('GET', '/client/api/test');
        $decoded = json_decode($response, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('success', $decoded);
        $this->assertTrue((bool)$decoded['success']);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertIsArray($decoded['data']);
        $this->assertNotEmpty($decoded['data']);
    }

    public function testApiGetEndpointAndNotFoundContract(): void
    {
        $okResponse = ClientModuleHarness::dispatch('GET', '/client/api/test/1');
        $okDecoded = json_decode($okResponse, true);
        $this->assertIsArray($okDecoded);
        $this->assertTrue((bool)$okDecoded['success']);
        $this->assertArrayHasKey('data', $okDecoded);
        $this->assertIsArray($okDecoded['data']);

        $notFoundResponse = ClientModuleHarness::dispatch('GET', '/client/api/test/999999');
        $notFoundDecoded = json_decode($notFoundResponse, true);
        $this->assertIsArray($notFoundDecoded);
        $this->assertFalse((bool)$notFoundDecoded['success']);
        $this->assertSame('Requested item was not found', $notFoundDecoded['message'] ?? null);
    }
}

