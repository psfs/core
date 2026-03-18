<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Collection\ArrayCollection;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\Request;
use PSFS\base\config\Config;
use PSFS\base\dto\Order;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\ResponseHelper;

class ApiCoverageTableMap extends TableMap
{
    public const TABLE_NAME = 'demo';
    public const DATABASE_NAME = 'default';

    /** @var array<string, ColumnMap> */
    private array $byPhpName = [];
    /** @var array<string, ColumnMap> */
    private array $byName = [];
    /** @var array<int|string, ColumnMap> */
    private array $pkList = [];
    /**
     * @param array<int|string, ColumnMap> $pkList
     */
    public function setPrimaryKeyList(array $pkList): void
    {
        $this->pkList = $pkList;
    }

    /**
     * @param array<int, ColumnMap> $columns
     */
    public function setColumnsList(array $columns): void
    {
        $this->byPhpName = [];
        $this->byName = [];
        foreach ($columns as $column) {
            $this->byPhpName[$column->getPhpName()] = $column;
            $this->byName[strtoupper($column->getName())] = $column;
        }
    }

    public function setPhpName(string $phpName): void
    {
        parent::setPhpName($phpName);
    }

    /**
     * @return array<int|string, ColumnMap>
     */
    public function getPrimaryKeys(): array
    {
        return $this->pkList;
    }

    /**
     * @return array<int, ColumnMap>
     */
    public function getColumns(): array
    {
        return array_values($this->byPhpName);
    }

    public function getColumnByPhpName(string $phpName): ColumnMap
    {
        if (!isset($this->byPhpName[$phpName])) {
            throw new \RuntimeException('Unknown column by php name: ' . $phpName);
        }
        return $this->byPhpName[$phpName];
    }

    public function hasColumn($column, bool $normalize = true): bool
    {
        return array_key_exists(strtoupper($column), $this->byName);
    }

    public function getColumn(string $column, bool $normalize = true): ColumnMap
    {
        $key = strtoupper($column);
        if (!isset($this->byName[$key])) {
            throw new \RuntimeException('Unknown column: ' . $column);
        }
        return $this->byName[$key];
    }

    public function getPhpName(): string
    {
        return parent::getPhpName() ?: 'Demo';
    }
}

class ApiCoverageTableMapClass
{
    public static ApiCoverageTableMap $tableMap;

    public static function getTableMap(): ApiCoverageTableMap
    {
        return self::$tableMap;
    }

    public static function getOMClass(bool $withPrefix = false): string
    {
        return \stdClass::class;
    }
}

class ApiCoverageDouble extends Api
{
    /** @var array<int, ActiveRecordInterface> */
    public array $bulkInput = [];
    public array $bulkExport = [];
    public int $bulkSavedPreset = 0;
    public ?ActiveRecordInterface $singleModel = null;
    public ?\Exception $postException = null;
    public mixed $paginateList = null;

    public function getModelTableMap()
    {
        return ApiCoverageTableMapClass::class;
    }

    public function init()
    {
        // no-op for unit tests
    }

    protected function hydrateRequestData()
    {
        // no-op for unit tests
    }

    protected function hydrateOrders()
    {
        if (!isset($this->order)) {
            $this->order = new Order(false);
        }
    }

    protected function checkFieldType()
    {
        // no-op for unit tests
    }

    protected function hydrateFromRequest()
    {
        if ($this->postException instanceof \Exception) {
            throw $this->postException;
        }
    }

    protected function hydrateBulkRequest()
    {
        $this->list = $this->bulkInput;
    }

    protected function saveBulk()
    {
        $this->bulkSavedCount = $this->bulkSavedPreset;
    }

    protected function exportList()
    {
        return $this->bulkExport;
    }

    protected function hydrateModel($primaryKey)
    {
        $this->model = $this->singleModel;
    }

    protected function _get($primaryKey)
    {
        return $this->singleModel;
    }

    protected function hydrateModelFromRequest(ActiveRecordInterface $model, array $data = [])
    {
        // no-op for unit tests
    }

    protected function paginate()
    {
        $this->list = $this->paginateList;
    }

    public function callExtractApiLang(): string
    {
        $this->extractApiLang();
        return $this->lang;
    }

    /**
     * @return array<string, string>
     */
    public function callGetPkDbName(): array
    {
        return $this->getPkDbName();
    }

    public function callFindPk(ModelCriteria $query, string $primaryKey): ?ActiveRecordInterface
    {
        return $this->findPk($query, $primaryKey);
    }

    public function callCheckReturnFields(ModelCriteria $query): void
    {
        $this->checkReturnFields($query);
    }

    /**
     * @param array<string, string> $extraColumns
     */
    public function setExtraColumns(array $extraColumns): void
    {
        $this->extraColumns = $extraColumns;
    }
}

class ApiCoverageModelCriteria extends ModelCriteria
{
    public array $primaryKeyCalls = [];
    public array $filterCalls = [];
    public array $selected = [];
    public mixed $findResult = null;

    public function __construct()
    {
        // no-op for tests
    }

    public function filterByPrimaryKey($primaryKey)
    {
        $this->primaryKeyCalls[] = $primaryKey;
        return $this;
    }

    public function filterBy($column, $value = null, $comparison = null)
    {
        $this->filterCalls[] = [$column, $value];
        return $this;
    }

    public function select($columnArray)
    {
        $this->selected = $columnArray;
        return $this;
    }

    public function find(?ConnectionInterface $con = null)
    {
        return $this->findResult;
    }
}

final class ApiCoreCoverageTest extends TestCase
{
    private array $configBackup = [];
    private array $serverBackup = [];
    private array $getBackup = [];
    private array $requestBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->requestBackup = $_REQUEST;

        Api::setTest(true);
        ResponseHelper::setTest(true);
        Config::setTest(true);

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/client/api/test',
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
        ];
        $_GET = [];
        $_REQUEST = [];
        Request::dropInstance();
        Request::getInstance()->init();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_REQUEST = $this->requestBackup;
        Request::dropInstance();
        Api::setTest(false);
        ResponseHelper::setTest(false);
        Config::setTest(false);
    }

    public function testBulkReturnsSavedCountInsteadOfInputSize(): void
    {
        $api = new ApiCoverageDouble();
        $model = $this->createMock(ActiveRecordInterface::class);
        $api->bulkInput = [$model, $model, $model];
        $api->bulkExport = [['Id' => 1], ['Id' => 2], ['Id' => 3]];
        $api->bulkSavedPreset = 2;

        $json = $api->bulk();
        $payload = json_decode($json, true);

        $this->assertTrue((bool)$payload['success']);
        $this->assertSame(2, (int)$payload['total']);
        $this->assertCount(3, $payload['data']);
    }

    public function testGetReturnsNotFoundWhenModelIsMissing(): void
    {
        $api = new ApiCoverageDouble();
        $api->singleModel = null;

        $json = $api->get('1');
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertSame([], $payload['data']);
        $this->assertSame('Requested item was not found', $payload['message']);
    }

    public function testModelListHandlesArrayCollectionPaginationPath(): void
    {
        $api = new ApiCoverageDouble();
        $api->paginateList = new ArrayCollection([
            ['Id' => 1],
            ['Id' => 2],
        ]);

        $json = $api->modelList();
        $payload = json_decode($json, true);

        $this->assertTrue((bool)$payload['success']);
        $this->assertSame(2, (int)$payload['total']);
        $this->assertSame(1, (int)$payload['pages']);
    }

    public function testExtractApiLangFallsBackToConfiguredDefaultLanguage(): void
    {
        Config::save(array_merge($this->configBackup, ['default.language' => 'en_GB']), []);
        Config::getInstance()->loadConfigData(true);

        $api = new ApiCoverageDouble();
        $this->assertSame('en_GB', $api->callExtractApiLang());
    }

    public function testGetPkDbNameSupportsSingleAndCompositePrimaryKeys(): void
    {
        $api = new ApiCoverageDouble();
        $tableMap = new ApiCoverageTableMap();

        $singlePk = $this->createMock(ColumnMap::class);
        $tableMap->setPrimaryKeyList(['ID' => $singlePk]);
        ApiCoverageTableMapClass::$tableMap = $tableMap;
        $single = $api->callGetPkDbName();
        $this->assertArrayHasKey('demo.ID', $single);
        $this->assertSame(Api::API_MODEL_KEY_FIELD, $single['demo.ID']);

        $pkA = $this->createMock(ColumnMap::class);
        $pkA->method('getName')->willReturn('ID');
        $pkA->method('getPhpName')->willReturn('Id');
        $pkB = $this->createMock(ColumnMap::class);
        $pkB->method('getName')->willReturn('DOMAIN_ID');
        $pkB->method('getPhpName')->willReturn('DomainId');
        $tableMap->setPrimaryKeyList([$pkA, $pkB]);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $composite = $api->callGetPkDbName();
        $this->assertArrayHasKey('demo.ID', $composite);
        $this->assertArrayHasKey('demo.DOMAIN_ID', $composite);
        $this->assertContains(Api::API_MODEL_KEY_FIELD, $composite);
    }

    public function testFindPkUsesPrimaryKeyAndCompositeFilters(): void
    {
        $api = new ApiCoverageDouble();
        $tableMap = new ApiCoverageTableMap();
        ApiCoverageTableMapClass::$tableMap = $tableMap;
        $model = $this->createMock(ActiveRecordInterface::class);
        $resultSet = new class($model) extends ArrayCollection {
            public function __construct(private readonly ActiveRecordInterface $first)
            {
                parent::__construct([]);
            }

            public function getFirst()
            {
                return $this->first;
            }
        };

        $singleQuery = new ApiCoverageModelCriteria();
        $singleQuery->findResult = $resultSet;

        $singlePk = $this->createMock(ColumnMap::class);
        $tableMap->setPrimaryKeyList(['ID' => $singlePk]);
        $this->assertSame($model, $api->callFindPk($singleQuery, '10'));
        $this->assertSame(['10'], $singleQuery->primaryKeyCalls);

        $pkA = $this->createMock(ColumnMap::class);
        $pkA->method('getName')->willReturn('ID');
        $pkA->method('getPhpName')->willReturn('Id');
        $pkB = $this->createMock(ColumnMap::class);
        $pkB->method('getName')->willReturn('DOMAIN_ID');
        $pkB->method('getPhpName')->willReturn('DomainId');
        $tableMap->setPrimaryKeyList([$pkA, $pkB]);

        $compositeQuery = new ApiCoverageModelCriteria();
        $compositeQuery->findResult = $resultSet;

        $this->assertSame($model, $api->callFindPk($compositeQuery, rawurlencode('10' . Api::API_PK_SEPARATOR . '20')));
        $this->assertSame([['Id', '10'], ['DomainId', '20']], $compositeQuery->filterCalls);
    }

    public function testCheckReturnFieldsSelectsOnlyKnownFieldsAndExtraColumns(): void
    {
        $column = $this->createMock(ColumnMap::class);
        $column->method('getPhpName')->willReturn('Name');
        $column->method('getName')->willReturn('NAME');
        $tableMap = new ApiCoverageTableMap();
        $tableMap->setColumnsList([$column]);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $_GET = [Api::API_FIELDS_RESULT_FIELD => 'Name,__name__,Unknown'];
        $_REQUEST = $_GET;
        Request::dropInstance();
        Request::getInstance()->init();

        $api = new ApiCoverageDouble();
        $api->setExtraColumns(['CONCAT("Demo #", demo.ID)' => Api::API_LIST_NAME_FIELD]);

        $query = new ApiCoverageModelCriteria();

        $api->callCheckReturnFields($query);
        $this->assertSame(['Name', Api::API_LIST_NAME_FIELD], $query->selected);
    }
}
