<?php

namespace PSFS\apitests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PSFS\apitests\support\ClientModuleHarness;
use PSFS\base\config\Config;

#[Group('api')]
#[Group('api-phase-c-http')]
#[Group('api-mysql')]
class ApiPhaseCHttpQueryContractTest extends TestCase
{
    private const POINT_1 = 'P1 happy list contract';
    private const POINT_6 = 'P6 combo search';
    private const POINT_7 = 'P7 ascending sort';
    private const POINT_9 = 'P9 multi-field sort';
    private const POINT_10 = 'P10 deterministic pagination';

    private static array $configBackup = [];

    public static function setUpBeforeClass(): void
    {
        ClientModuleHarness::acquire();
        self::$configBackup = Config::getInstance()->dumpConfig();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$configBackup !== []) {
            Config::save(self::$configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
        ClientModuleHarness::release();
    }

    protected function setUp(): void
    {
        ClientModuleHarness::resetSeedData();
        Config::save(self::$configBackup, []);
        Config::getInstance()->loadConfigData(true);
    }

    public function testPoint1ListHappyPathReturnsStableContract(): void
    {
        $response = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test'));

        $this->assertTrue((bool)$response['success'], self::POINT_1);
        $this->assertArrayHasKey('data', $response, self::POINT_1);
        $this->assertIsArray($response['data'], self::POINT_1);
        $this->assertNotEmpty($response['data'], self::POINT_1);
        $this->assertArrayHasKey('total', $response, self::POINT_1);
        $this->assertArrayHasKey('pages', $response, self::POINT_1);
    }

    public function testPoint10PaginationHasNoOverlapAndStableOrder(): void
    {
        $order = rawurlencode(json_encode(['Id' => 'asc']));
        $page1 = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test?__limit=20&__page=1&__order=' . $order));
        $page2 = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test?__limit=20&__page=2&__order=' . $order));

        $this->assertTrue((bool)$page1['success'], self::POINT_10);
        $this->assertTrue((bool)$page2['success'], self::POINT_10);
        $this->assertCount(20, $page1['data']);
        $this->assertCount(20, $page2['data']);
        $this->assertGreaterThan(40, (int)$page1['total']);
        $this->assertGreaterThan(1, (int)$page1['pages']);

        $ids1 = $this->extractIds($page1['data']);
        $ids2 = $this->extractIds($page2['data']);
        $this->assertSame([], array_values(array_intersect($ids1, $ids2)));
        $this->assertIsSortedById($page1['data']);
        $this->assertIsSortedById($page2['data']);
    }

    public function testListSupportsExactAndCompoundFilters(): void
    {
        $order = rawurlencode(json_encode(['Id' => 'asc']));
        $response = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test?Type=DEV&Checker=1&__order=' . $order));
        $this->assertTrue((bool)$response['success']);
        $this->assertNotEmpty($response['data']);
        foreach ($response['data'] as $item) {
            $this->assertSame('DEV', (string)($item['Type'] ?? ''));
            $this->assertContains((int)($item['Checker'] ?? 0), [1, true]);
        }
    }

    public function testListSupportsQuotedLikeFilters(): void
    {
        $query = rawurlencode('"critical path"');
        $response = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test?Summary=' . $query . '&__limit=50'));
        $this->assertTrue((bool)$response['success']);
        $this->assertNotEmpty($response['data']);
        foreach ($response['data'] as $item) {
            $summary = strtolower((string)($item['Summary'] ?? ''));
            $this->assertStringContainsString('critical', $summary);
            $this->assertStringContainsString('path', $summary);
        }
    }

    public function testListSupportsComboSearch(): void
    {
        $response = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test?__combo=fixture&__limit=50'));
        $this->assertTrue((bool)$response['success'], self::POINT_6);
        $this->assertNotEmpty($response['data']);
        $this->assertLessThanOrEqual(50, count($response['data']));
    }

    public function testPoint7ListSupportsAscendingSortWithPkTieBreaker(): void
    {
        $order = rawurlencode(json_encode(['Number' => 'asc', 'Id' => 'asc']));
        $response = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test?__limit=60&__order=' . $order));

        $this->assertTrue((bool)$response['success'], self::POINT_7);
        $this->assertNotEmpty($response['data'], self::POINT_7);
        $this->assertIsSortedByNumberThenId($response['data'], true);
    }

    public function testPoint9ListSupportsMultiFieldSort(): void
    {
        $order = rawurlencode(json_encode(['Type' => 'asc', 'Number' => 'desc', 'Id' => 'asc']));
        $response = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test?__limit=60&__order=' . $order));
        $this->assertTrue((bool)$response['success'], self::POINT_9);
        $this->assertNotEmpty($response['data'], self::POINT_9);
        $this->assertIsSortedByTypeNumberAndId($response['data']);
    }

    public function testListSupportsFieldProjectionWhenApiFieldTypesEnabled(): void
    {
        $config = array_merge(self::$configBackup, [
            'api.field.types' => true,
            'api.extrafields.compat' => false,
        ]);
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $response = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test?__fields=Id,Number&__limit=5&__order=' . rawurlencode(json_encode(['Id' => 'asc']))));
        $this->assertTrue((bool)$response['success']);
        $this->assertCount(5, $response['data']);
        foreach ($response['data'] as $item) {
            $this->assertArrayHasKey('Id', $item);
            $this->assertArrayHasKey('Number', $item);
        }
    }

    public function testGetRespectsApiLangHeaderForI18nField(): void
    {
        $en = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test/1', ['HTTP_X_API_LANG' => 'en']));
        $es = $this->decodeJsonResponse(ClientModuleHarness::dispatch('GET', '/client/api/test/1', ['HTTP_X_API_LANG' => 'es']));

        $this->assertTrue((bool)$en['success']);
        $this->assertTrue((bool)$es['success']);
        $this->assertSame('Base name', (string)($en['data']['Name'] ?? ''));
        $this->assertSame('Nombre base', (string)($es['data']['Name'] ?? ''));
    }

    public function testFastProfileBlocksUnboundedLimit(): void
    {
        $prevProfile = getenv('PSFS_TEST_PROFILE');
        $prevAllow = getenv('PSFS_ALLOW_UNBOUNDED_LIST');
        putenv('PSFS_TEST_PROFILE=ci');
        putenv('PSFS_ALLOW_UNBOUNDED_LIST');
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unbounded __limit=-1 is disabled in fast profile');
            ClientModuleHarness::dispatch('GET', '/client/api/test?__limit=-1');
        } finally {
            if ($prevProfile === false) {
                putenv('PSFS_TEST_PROFILE');
            } else {
                putenv('PSFS_TEST_PROFILE=' . $prevProfile);
            }
            if ($prevAllow === false) {
                putenv('PSFS_ALLOW_UNBOUNDED_LIST');
            } else {
                putenv('PSFS_ALLOW_UNBOUNDED_LIST=' . $prevAllow);
            }
        }
    }

    /**
     * @param string $response
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(string $response): array
    {
        $decoded = json_decode($response, true);
        $this->assertIsArray($decoded, 'API response is not valid JSON: ' . substr($response, 0, 200));
        return $decoded;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, int>
     */
    private function extractIds(array $rows): array
    {
        return array_map(static fn(array $row): int => (int)($row['Id'] ?? 0), $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return void
     */
    private function assertIsSortedById(array $rows): void
    {
        $previous = null;
        foreach ($rows as $row) {
            $id = (int)($row['Id'] ?? 0);
            if ($previous !== null) {
                $this->assertGreaterThanOrEqual($previous, $id);
            }
            $previous = $id;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param bool $asc
     * @return void
     */
    private function assertIsSortedByNumberThenId(array $rows, bool $asc): void
    {
        $previousNumber = null;
        $previousId = null;
        foreach ($rows as $row) {
            $number = (int)($row['Number'] ?? 0);
            $id = (int)($row['Id'] ?? 0);
            if ($previousNumber !== null) {
                if ($asc) {
                    $this->assertTrue(
                        $number > $previousNumber || ($number === $previousNumber && $id >= (int)$previousId),
                        'Rows are not sorted ascending by Number, Id'
                    );
                } else {
                    $this->assertTrue(
                        $number < $previousNumber || ($number === $previousNumber && $id >= (int)$previousId),
                        'Rows are not sorted descending by Number, Id'
                    );
                }
            }
            $previousNumber = $number;
            $previousId = $id;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return void
     */
    private function assertIsSortedByTypeNumberAndId(array $rows): void
    {
        $previousType = null;
        $previousNumber = null;
        $previousId = null;
        foreach ($rows as $row) {
            $type = (string)($row['Type'] ?? '');
            $number = (int)($row['Number'] ?? 0);
            $id = (int)($row['Id'] ?? 0);
            if ($previousType !== null) {
                if ($type === $previousType) {
                    if ($number === (int)$previousNumber) {
                        $this->assertGreaterThanOrEqual((int)$previousId, $id, 'Rows are not sorted by Type asc, Number desc, Id asc');
                    } else {
                        $this->assertLessThanOrEqual((int)$previousNumber, $number, 'Rows are not sorted by Type asc, Number desc');
                    }
                } else {
                    $this->assertLessThanOrEqual($type, $previousType, 'Rows are not sorted ascending by Type');
                }
            }
            $previousType = $type;
            $previousNumber = $number;
            $previousId = $id;
        }
    }
}
