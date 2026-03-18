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
        return ApiCoverageActiveRecord::class;
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
    public ?\Exception $bulkException = null;
    public mixed $paginateList = null;
    public ?ActiveRecordInterface $postModel = null;

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
        if ($this->postModel instanceof ActiveRecordInterface) {
            $this->model = $this->postModel;
        }
    }

    protected function hydrateBulkRequest()
    {
        if ($this->bulkException instanceof \Exception) {
            throw $this->bulkException;
        }
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

    public function callHydrateFromRequest(): void
    {
        $this->hydrateFromRequest();
    }

    public function callHydrateBulkRequest(): void
    {
        $this->hydrateBulkRequest();
    }

    /**
     * @return array<int, mixed>
     */
    public function callExportList(): array
    {
        return $this->exportList();
    }

    public function callGetApi(): string
    {
        return (string)$this->getApi();
    }

    public function callGetDomain(): string
    {
        return (string)$this->getDomain();
    }

    /**
     * @return array<string, mixed>
     */
    public function callRenderModel(): array
    {
        return $this->renderModel();
    }

    /**
     * @return ActiveRecordInterface|null
     */
    public function callGetModelInternal()
    {
        return $this->getModel();
    }

    public function callAddExtraColumns(ModelCriteria $query, string $action): void
    {
        $reflection = new \ReflectionMethod($this, 'addExtraColumns');
        $reflection->setAccessible(true);
        $reflection->invokeArgs($this, [&$query, $action]);
    }

    /**
     * @param array<string, string> $extraColumns
     */
    public function setExtraColumns(array $extraColumns): void
    {
        $this->extraColumns = $extraColumns;
    }

    /**
     * @return array<string, string>
     */
    public function getExtraColumns(): array
    {
        return $this->extraColumns;
    }

    public function setModelForTests(?ActiveRecordInterface $model): void
    {
        $this->model = $model;
    }

    public function setConnectionForTests(object $con): void
    {
        $this->con = $con;
    }

    protected function closeTransaction($status)
    {
        if ($this->con instanceof ConnectionInterface) {
            parent::closeTransaction($status);
        }
    }

    /**
     * @param array<int, string> $pkTokens
     */
    public function callHasSinglePrimaryKeyToken(array $pkTokens): bool
    {
        return $this->hasSinglePrimaryKeyToken($pkTokens);
    }

    /**
     * @return array<int, string>
     */
    public function callParsePrimaryKeyTokens(string $primaryKey): array
    {
        return $this->parsePrimaryKeyTokens($primaryKey);
    }

    /**
     * @return array<int, string>
     */
    public function callResolveRequestedExtraFields(): array
    {
        return $this->resolveRequestedExtraFields();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function callResolveLocaleFromInput(array $data): string
    {
        return $this->resolveLocaleFromInput($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function callCleanData(array $data): array
    {
        $this->cleanData($data);
        return $data;
    }
}

class ApiCoverageTraitDouble extends Api
{
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

    protected function paginate()
    {
        // no-op for unit tests
    }

    public function setDataForTests(array $data): void
    {
        $this->data = $data;
    }

    public function callHydrateFromRequest(): void
    {
        $this->hydrateFromRequest();
    }

    public function callHydrateBulkRequest(): void
    {
        $this->hydrateBulkRequest();
    }

    public function callExtractFieldsForTests(): void
    {
        $this->extractFields();
    }

    public function callJoinTablesForTests(ModelCriteria $query): void
    {
        $this->joinTables($query);
    }

    public function callHydrateModelForTests(string $primaryKey): void
    {
        $this->hydrateModel($primaryKey);
    }

    public function getModelForTests(): ?ActiveRecordInterface
    {
        return $this->model;
    }

    /**
     * @return array<int, mixed>
     */
    public function exportListForTests(): array
    {
        return $this->exportList();
    }

    /**
     * @return array<int, ActiveRecordInterface>
     */
    public function getListModelsForTests(): array
    {
        return $this->list;
    }
}

class ApiCoverageTraitHydrateExceptionDouble extends ApiCoverageTraitDouble
{
    protected function prepareQuery()
    {
        throw new \RuntimeException('forced hydrate model failure');
    }
}

class ApiCoverageModelCriteria extends ModelCriteria
{
    public array $primaryKeyCalls = [];
    public array $filterCalls = [];
    public array $selected = [];
    public mixed $findResult = null;
    public array $withColumns = [];
    public ?string $throwOnFilterByField = null;

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
        if ($this->throwOnFilterByField !== null && $column === $this->throwOnFilterByField) {
            throw new \RuntimeException('forced filterBy failure');
        }
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

    public function withColumn(string $clause, ?string $name = null)
    {
        $this->withColumns[] = [$clause, $name];
        return $this;
    }
}

class ApiCoverageActiveRecord implements ActiveRecordInterface
{
    public bool $primaryKeyNull = false;
    public bool $saveResult = true;
    public bool $deleteCalled = false;
    public bool $throwOnDelete = false;
    public array $arrayResult = [];
    public ?string $locale = null;
    public array $receivedFromArray = [];

    public function isPrimaryKeyNull(): bool
    {
        return $this->primaryKeyNull;
    }

    public function save($con = null)
    {
        return $this->saveResult;
    }

    public function toArray($keyType = TableMap::TYPE_FIELDNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = [], $includeForeignObjects = false): array
    {
        return $this->arrayResult;
    }

    public function fromArray(array $arr, $keyType = TableMap::TYPE_PHPNAME)
    {
        $this->receivedFromArray = $arr;
        return $this;
    }

    public function setLocale($locale)
    {
        $this->locale = (string)$locale;
        return $this;
    }

    public function clearAllReferences($deep = false): void
    {
    }

    public function delete($con = null): void
    {
        if ($this->throwOnDelete) {
            throw new \RuntimeException('delete failure');
        }
        $this->deleteCalled = true;
    }
}

class ApiCoverageConnectionFake
{
    public bool $transaction = false;
    public int $beginCalls = 0;
    public int $commitCalls = 0;
    public int $rollbackCalls = 0;

    public function beginTransaction(): void
    {
        $this->beginCalls++;
        $this->transaction = true;
    }

    public function commit(): void
    {
        $this->commitCalls++;
        $this->transaction = false;
    }

    public function rollBack(): void
    {
        $this->rollbackCalls++;
        $this->transaction = false;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }

    public function getLastExecutedQuery(): string
    {
        return '';
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
        $model = new ApiCoverageActiveRecord();
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

    public function testGetReturnsModelWhenFound(): void
    {
        $api = new ApiCoverageDouble();
        $model = new ApiCoverageActiveRecord();
        $model->arrayResult = ['Id' => 1];
        $api->singleModel = $model;

        $json = $api->get('1');
        $payload = json_decode($json, true);

        $this->assertTrue((bool)$payload['success']);
        $this->assertSame(['Id' => 1], $payload['data']);
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

    public function testModelListReturnsMessageWhenNoItemsFound(): void
    {
        $api = new ApiCoverageDouble();
        $api->paginateList = new ArrayCollection([]);

        $json = $api->modelList();
        $payload = json_decode($json, true);

        $this->assertTrue((bool)$payload['success']);
        $this->assertSame('No items found for the search', $payload['message']);
    }

    public function testExtractApiLangFallsBackToConfiguredDefaultLanguage(): void
    {
        Config::save(array_merge($this->configBackup, ['default.language' => 'en_GB']), []);
        Config::getInstance()->loadConfigData(true);

        $api = new ApiCoverageDouble();
        $this->assertSame('en_GB', $api->callExtractApiLang());
    }

    public function testParsePrimaryKeyTokensAndSingleKeyDetection(): void
    {
        $api = new ApiCoverageDouble();
        $single = $api->callParsePrimaryKeyTokens('10');
        $composite = $api->callParsePrimaryKeyTokens(rawurlencode('10' . Api::API_PK_SEPARATOR . '20'));

        $this->assertTrue($api->callHasSinglePrimaryKeyToken($single));
        $this->assertFalse($api->callHasSinglePrimaryKeyToken($composite));
        $this->assertSame(['10', '20'], $composite);
    }

    public function testFindPkCompositeContinuesWhenOneFilterFails(): void
    {
        $api = new ApiCoverageDouble();
        $tableMap = new ApiCoverageTableMap();
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $pkA = $this->createMock(ColumnMap::class);
        $pkA->method('getName')->willReturn('ID');
        $pkA->method('getPhpName')->willReturn('Id');
        $pkB = $this->createMock(ColumnMap::class);
        $pkB->method('getName')->willReturn('DOMAIN_ID');
        $pkB->method('getPhpName')->willReturn('DomainId');
        $tableMap->setPrimaryKeyList([$pkA, $pkB]);

        $model = new ApiCoverageActiveRecord();
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

        $query = new ApiCoverageModelCriteria();
        $query->throwOnFilterByField = 'Id';
        $query->findResult = $resultSet;

        $this->assertSame($model, $api->callFindPk($query, rawurlencode('10' . Api::API_PK_SEPARATOR . '20')));
        $this->assertSame([['DomainId', '10'], [Api::API_MODEL_KEY_FIELD, '20']], $query->filterCalls);
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
        $model = new ApiCoverageActiveRecord();
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

    public function testCheckReturnFieldsIgnoresUnknownSelection(): void
    {
        $tableMap = new ApiCoverageTableMap();
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $_GET = [Api::API_FIELDS_RESULT_FIELD => 'Unknown,Nope'];
        $_REQUEST = $_GET;
        Request::dropInstance();
        Request::getInstance()->init();

        $api = new ApiCoverageDouble();
        $query = new ApiCoverageModelCriteria();
        $api->callCheckReturnFields($query);

        $this->assertSame([], $query->selected);
    }

    public function testPostReturnsDebugMessageWhenDebugEnabled(): void
    {
        Config::save(array_merge($this->configBackup, ['debug' => true]), []);
        Config::getInstance()->loadConfigData(true);

        $api = new ApiCoverageDouble();
        $api->setModelForTests(new ApiCoverageActiveRecord());
        $api->postException = new \RuntimeException('failure detail', 321);

        $json = $api->post();
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertStringContainsString('failure detail', (string)$payload['message']);
    }

    public function testPostReturnsSavedModelWhenSuccessful(): void
    {
        $api = new ApiCoverageDouble();
        $model = new ApiCoverageActiveRecord();
        $model->saveResult = true;
        $model->arrayResult = ['Id' => 99];
        $api->postModel = $model;

        $json = $api->post();
        $payload = json_decode($json, true);

        $this->assertTrue((bool)$payload['success']);
        $this->assertSame(['Id' => 99], $payload['data']);
    }

    public function testPostReturnsCodeMessageWhenDebugDisabled(): void
    {
        Config::save(array_merge($this->configBackup, ['debug' => false]), []);
        Config::getInstance()->loadConfigData(true);

        $api = new ApiCoverageDouble();
        $api->setModelForTests(new ApiCoverageActiveRecord());
        $api->postException = new \RuntimeException('failure detail', 654);

        $json = $api->post();
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertStringContainsString('654', (string)$payload['message']);
    }

    public function testPostReturnsErrorMessageWhenSaveReturnsFalse(): void
    {
        $api = new ApiCoverageDouble();
        $model = new ApiCoverageActiveRecord();
        $model->saveResult = false;
        $api->postModel = $model;

        $json = $api->post();
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertSame('Selected model could not be saved', $payload['message']);
    }

    public function testPutReturnsNotFoundMessageWhenModelIsMissing(): void
    {
        $api = new ApiCoverageDouble();
        $api->singleModel = null;

        $json = $api->put('10');
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertSame('Referenced model for update was not found', $payload['message']);
    }

    public function testPutReturnsUpdatedModelOnSuccess(): void
    {
        $api = new ApiCoverageDouble();
        $model = new ApiCoverageActiveRecord();
        $model->saveResult = true;
        $model->arrayResult = ['Id' => 10, 'Name' => 'updated'];
        $api->singleModel = $model;

        $json = $api->put('10');
        $payload = json_decode($json, true);

        $this->assertTrue((bool)$payload['success']);
        $this->assertSame(['Id' => 10, 'Name' => 'updated'], $payload['data']);
    }

    public function testPutReturnsErrorMessageWhenSaveReturnsFalse(): void
    {
        $api = new ApiCoverageDouble();
        $model = new ApiCoverageActiveRecord();
        $model->saveResult = false;
        $api->singleModel = $model;

        $json = $api->put('10');
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertSame('An error occurred while updating the item, please check logs', $payload['message']);
    }

    public function testBulkReturnsRollbackMessageOnException(): void
    {
        $api = new ApiCoverageDouble();
        $api->bulkException = new \RuntimeException('bulk fail');

        $json = $api->bulk();
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertSame('Bulk insert rolled back', $payload['message']);
    }

    public function testDeleteWithoutPrimaryKeyReturnsFailureContract(): void
    {
        $api = new ApiCoverageDouble();

        $json = $api->delete(null);
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertSame(0, (int)$payload['total']);
    }

    public function testDeleteWithPrimaryKeyReturnsSuccessWhenModelDeletes(): void
    {
        $api = new ApiCoverageDouble();
        $api->singleModel = new ApiCoverageActiveRecord();
        $con = new ApiCoverageConnectionFake();
        $api->setConnectionForTests($con);

        $json = $api->delete('10');
        $payload = json_decode($json, true);

        $this->assertTrue((bool)$payload['success']);
        $this->assertTrue($api->singleModel->deleteCalled);
        $this->assertSame(1, $con->beginCalls);
    }

    public function testDeleteReturnsFailureWhenModelDeleteThrows(): void
    {
        $api = new ApiCoverageDouble();
        $model = new ApiCoverageActiveRecord();
        $model->throwOnDelete = true;
        $api->singleModel = $model;
        $con = new ApiCoverageConnectionFake();
        $api->setConnectionForTests($con);

        $json = $api->delete('10');
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertSame(1, $con->beginCalls);
    }

    public function testHydrateFromRequestCreatesModelAndHydratesPayload(): void
    {
        $api = new ApiCoverageTraitDouble();
        $api->setDataForTests(['Name' => 'Alice']);

        $api->callHydrateFromRequest();
        $model = $api->getModelForTests();

        $this->assertInstanceOf(ApiCoverageActiveRecord::class, $model);
        $this->assertSame(['Name' => 'Alice'], $model->receivedFromArray);
    }

    public function testHydrateBulkRequestHydratesListItems(): void
    {
        Config::save(array_merge($this->configBackup, ['api.block.limit' => 3]), []);
        Config::getInstance()->loadConfigData(true);

        $api = new ApiCoverageTraitDouble();
        $api->setDataForTests([
            ['Name' => 'one'],
            ['Name' => 'two'],
            ['Name' => 'three'],
        ]);

        $api->callHydrateBulkRequest();
        $models = $api->getListModelsForTests();

        $this->assertCount(3, $models);
        $this->assertSame('one', $models[0]->receivedFromArray['Name'] ?? null);
        $this->assertSame('two', $models[1]->receivedFromArray['Name'] ?? null);
        $this->assertSame('three', $models[2]->receivedFromArray['Name'] ?? null);
    }

    public function testHydrateBulkRequestRespectsConfiguredLimit(): void
    {
        Config::save(array_merge($this->configBackup, ['api.block.limit' => 1]), []);
        Config::getInstance()->loadConfigData(true);

        $api = new ApiCoverageTraitDouble();
        $api->setDataForTests([
            ['Name' => 'one'],
            ['Name' => 'two'],
            'not-an-array',
        ]);

        $api->callHydrateBulkRequest();
        $models = $api->getListModelsForTests();

        $this->assertCount(1, $models);
        $this->assertSame('one', $models[0]->receivedFromArray['Name'] ?? null);
    }

    public function testTraitPlaceholderMethodsAndHydrateModelCatchPath(): void
    {
        $api = new ApiCoverageTraitDouble();
        $api->callExtractFieldsForTests();
        $api->callJoinTablesForTests(new ApiCoverageModelCriteria());
        $this->assertTrue(true);

        $failing = new ApiCoverageTraitHydrateExceptionDouble();
        $failing->callHydrateModelForTests('10');
        $this->assertNull($failing->getModelForTests());
    }

    public function testRenderModelAndApiDomainHelpers(): void
    {
        $api = new ApiCoverageDouble();
        $model = new ApiCoverageActiveRecord();
        $model->arrayResult = ['Id' => 77];
        $api->setModelForTests($model);

        $this->assertSame(['Id' => 77], $api->callRenderModel());
        $this->assertSame('ApiCoverageActiveRecord', $api->callGetApi());
        $this->assertSame('PSFS', $api->callGetDomain());
    }

    public function testAddExtraColumnsInCompatModeAddsAllLegacyColumns(): void
    {
        Config::save(array_merge($this->configBackup, ['api.extrafields.compat' => true]), []);
        Config::getInstance()->loadConfigData(true);

        $nameColumn = $this->createMock(ColumnMap::class);
        $nameColumn->method('getPhpName')->willReturn('Name');
        $nameColumn->method('getName')->willReturn('NAME');
        $nameColumn->method('getFullyQualifiedName')->willReturn('demo.NAME');

        $pk = $this->createMock(ColumnMap::class);
        $pk->method('getName')->willReturn('ID');
        $pk->method('getPhpName')->willReturn('Id');
        $pk->method('getFullyQualifiedName')->willReturn('demo.ID');

        $tableMap = new ApiCoverageTableMap();
        $tableMap->setColumnsList([$nameColumn]);
        $tableMap->setPrimaryKeyList(['ID' => $pk]);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $api = new ApiCoverageDouble();
        $query = new ApiCoverageModelCriteria();
        $api->callAddExtraColumns($query, Api::API_ACTION_LIST);

        $this->assertNotEmpty($query->withColumns);
        $this->assertArrayHasKey('demo.NAME', $api->getExtraColumns());
        $this->assertContains(Api::API_MODEL_KEY_FIELD, $api->getExtraColumns());
        $this->assertContains(Api::API_LIST_NAME_FIELD, $api->getExtraColumns());
    }

    public function testResolveRequestedExtraFieldsInNonCompatAddsPkToken(): void
    {
        Config::save(array_merge($this->configBackup, ['api.extrafields.compat' => false]), []);
        Config::getInstance()->loadConfigData(true);

        $_GET = [Api::API_FIELDS_RESULT_FIELD => Api::API_LIST_NAME_FIELD];
        $_REQUEST = $_GET;
        Request::dropInstance();
        Request::getInstance()->init();

        $api = new ApiCoverageDouble();
        $fields = $api->callResolveRequestedExtraFields();

        $this->assertContains(Api::API_LIST_NAME_FIELD, $fields);
        $this->assertContains(Api::API_MODEL_KEY_FIELD, $fields);
    }

    public function testResolveRequestedExtraFieldsInCompatModeReturnsExtraColumnAliases(): void
    {
        Config::save(array_merge($this->configBackup, ['api.extrafields.compat' => true]), []);
        Config::getInstance()->loadConfigData(true);

        $api = new ApiCoverageDouble();
        $api->setExtraColumns([
            'demo.NAME' => Api::API_LIST_NAME_FIELD,
            'demo.ID' => Api::API_MODEL_KEY_FIELD,
        ]);

        $fields = $api->callResolveRequestedExtraFields();

        $this->assertSame([Api::API_LIST_NAME_FIELD, Api::API_MODEL_KEY_FIELD], $fields);
    }

    public function testAddExtraColumnsNonCompatRespectsRequestedFieldsAndAlwaysIncludesPk(): void
    {
        Config::save(array_merge($this->configBackup, ['api.extrafields.compat' => false]), []);
        Config::getInstance()->loadConfigData(true);

        $nameColumn = $this->createMock(ColumnMap::class);
        $nameColumn->method('getPhpName')->willReturn('Name');
        $nameColumn->method('getName')->willReturn('NAME');
        $nameColumn->method('getFullyQualifiedName')->willReturn('demo.NAME');

        $pk = $this->createMock(ColumnMap::class);
        $pk->method('getName')->willReturn('ID');
        $pk->method('getPhpName')->willReturn('Id');
        $pk->method('getFullyQualifiedName')->willReturn('demo.ID');

        $tableMap = new ApiCoverageTableMap();
        $tableMap->setColumnsList([$nameColumn]);
        $tableMap->setPrimaryKeyList(['ID' => $pk]);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $_GET = [Api::API_FIELDS_RESULT_FIELD => Api::API_LIST_NAME_FIELD];
        $_REQUEST = $_GET;
        Request::dropInstance();
        Request::getInstance()->init();

        $api = new ApiCoverageDouble();
        $query = new ApiCoverageModelCriteria();
        $api->callAddExtraColumns($query, Api::API_ACTION_LIST);

        $aliases = array_map(static fn(array $item): ?string => $item[1], $query->withColumns);
        $this->assertContains(Api::API_LIST_NAME_FIELD, $aliases);
        $this->assertContains(Api::API_MODEL_KEY_FIELD, $aliases);
    }

    public function testResolveLocaleFromInputPrecedence(): void
    {
        Config::save(array_merge($this->configBackup, ['default.language' => 'en_GB']), []);
        Config::getInstance()->loadConfigData(true);

        $api = new ApiCoverageDouble();
        $_SERVER['HTTP_X_API_LANG'] = 'es';
        Request::dropInstance();
        Request::getInstance()->init();

        $this->assertSame('de_DE', $api->callResolveLocaleFromInput(['Locale' => 'de_DE']));
        $this->assertSame('fr_FR', $api->callResolveLocaleFromInput(['locale' => 'fr_FR']));
        $this->assertSame('es', $api->callResolveLocaleFromInput([]));
    }

    public function testResolveLocaleFromInputFallsBackToDefaultLanguageWithoutHeader(): void
    {
        Config::save(array_merge($this->configBackup, ['default.language' => 'pt_PT']), []);
        Config::getInstance()->loadConfigData(true);

        unset($_SERVER['HTTP_X_API_LANG']);
        Request::dropInstance();
        Request::getInstance()->init();

        $api = new ApiCoverageDouble();
        $this->assertSame('pt_PT', $api->callResolveLocaleFromInput([]));
    }

    public function testCleanDataRemovesScriptAndIframeRecursively(): void
    {
        $api = new ApiCoverageDouble();
        $payload = [
            'name' => '<script>alert(1)</script>safe',
            'nested' => [
                'bio' => '<iframe src=\"x\"></iframe>ok',
            ],
        ];

        $cleaned = $api->callCleanData($payload);

        $this->assertSame('safe', $cleaned['name']);
        $this->assertSame('ok', $cleaned['nested']['bio']);
    }
}
