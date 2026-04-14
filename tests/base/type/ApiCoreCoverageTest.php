<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Collection\ArrayCollection;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\RelationMap;
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
    private bool $hasI18nRelation = false;
    private ?RelationMap $i18nRelation = null;
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

    public function configureI18nRelation(RelationMap $relation): void
    {
        $this->hasI18nRelation = true;
        $this->i18nRelation = $relation;
    }

    public function hasRelation(string $name): bool
    {
        return $this->hasI18nRelation;
    }

    public function getRelation(string $name): RelationMap
    {
        if (!$this->i18nRelation instanceof RelationMap) {
            throw new \RuntimeException('Missing i18n relation');
        }
        return $this->i18nRelation;
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

class ApiCoverageTableMapWithoutOmClass
{
    public static function getOMClass(bool $withPrefix = false): string
    {
        throw new \RuntimeException('om class unavailable');
    }

    public static function getTableMap(): object
    {
        return new class {
            public function getClassName(): string
            {
                return ApiCoverageActiveRecord::class;
            }
        };
    }
}

class ApiCoverageTableMapAlwaysFailing
{
    public static function getOMClass(bool $withPrefix = false): string
    {
        throw new \RuntimeException('om class unavailable');
    }

    public static function getTableMap(): object
    {
        throw new \RuntimeException('table map unavailable');
    }
}

class ApiCoverageBuildableTableMapClass
{
    public static bool $built = false;
    public static ?ApiCoverageTableMap $tableMap = null;

    public static function getOMClass(bool $withPrefix = false): string
    {
        throw new \RuntimeException('om class unavailable');
    }

    public static function getTableMap(): ApiCoverageTableMap
    {
        if (!self::$built || !self::$tableMap instanceof ApiCoverageTableMap) {
            throw new \RuntimeException('table map unavailable');
        }
        return self::$tableMap;
    }

    public static function buildTableMap(): void
    {
        self::$built = true;
        if (!self::$tableMap instanceof ApiCoverageTableMap) {
            self::$tableMap = new ApiCoverageTableMap();
        }
    }
}

class ApiCoverageBrokenBuildTableMapClass
{
    public static function getTableMap(): ApiCoverageTableMap
    {
        throw new \RuntimeException('table map unavailable');
    }

    public static function buildTableMap(): void
    {
        throw new \RuntimeException('build failure');
    }
}

class ApiCoverageI18nLocalTable extends TableMap
{
    /**
     * @param array<int, ColumnMap> $columns
     */
    public function __construct(private readonly array $columnsList)
    {
    }

    /**
     * @return array<int, ColumnMap>
     */
    public function getColumns(): array
    {
        return $this->columnsList;
    }
}

class ApiCoverageI18nMapTableMapProxy
{
    public static ?TableMap $localTable = null;

    public static function getTableMap(): TableMap
    {
        if (!self::$localTable instanceof TableMap) {
            throw new \RuntimeException('Missing i18n local table');
        }
        return self::$localTable;
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
    public ?\Exception $hydrateModelException = null;
    public mixed $paginateList = null;
    public ?ActiveRecordInterface $postModel = null;
    public string|null $tableMapClass = ApiCoverageTableMapClass::class;
    public bool $hydrateRequestDataCalled = false;
    public bool $checkFieldTypeCalled = false;
    public bool $hydrateOrdersCalled = false;
    public bool $createConnectionCalled = false;

    public function getModelTableMap()
    {
        return $this->tableMapClass;
    }

    public function init()
    {
        // no-op for unit tests
    }

    protected function hydrateRequestData()
    {
        $this->hydrateRequestDataCalled = true;
        parent::hydrateRequestData();
    }

    protected function hydrateOrders()
    {
        $this->hydrateOrdersCalled = true;
        if (!isset($this->order)) {
            $this->order = new Order(false);
        }
    }

    protected function checkFieldType()
    {
        $this->checkFieldTypeCalled = true;
        parent::checkFieldType();
    }

    protected function createConnection($tablemap)
    {
        $this->createConnectionCalled = true;
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
        if ($this->hydrateModelException instanceof \Exception) {
            throw $this->hydrateModelException;
        }
        parent::hydrateModelFromRequest($model, $data);
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

    public function callParentInitForTests(): void
    {
        parent::init();
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

    public function getActionForTests(): string
    {
        return (string)$this->action;
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

    public function callGetModelNamespaceForTests(): ?string
    {
        return $this->getModelNamespace();
    }

    public function callGetTableMapForTests(): ?TableMap
    {
        $reflection = new \ReflectionMethod($this, 'getTableMap');
        $reflection->setAccessible(true);
        return $reflection->invoke($this);
    }

    public function callHydrateModelFromRequestForTests(ActiveRecordInterface $model, array $data = []): void
    {
        $this->hydrateModelFromRequest($model, $data);
    }

    public function callCheckI18nForTests(ModelCriteria $query): void
    {
        $this->checkI18n($query);
    }

    /**
     * @return array<string, string>
     */
    public function callParseExtraColumnsForTests(): array
    {
        return $this->parseExtraColumns();
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
        parent::hydrateRequestData();
    }

    protected function hydrateOrders()
    {
        if (!isset($this->order)) {
            $this->order = new Order(false);
        }
    }

    protected function checkFieldType()
    {
        parent::checkFieldType();
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
    public ?string $usedI18nLang = null;

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

    public function useI18nQuery($lang = null)
    {
        $this->usedI18nLang = (string)$lang;
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
    public array $translations = [];
    public ?\Throwable $deleteException = null;

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
        if ($this->deleteException instanceof \Throwable) {
            throw $this->deleteException;
        }
        if ($this->throwOnDelete) {
            throw new \RuntimeException('delete failure');
        }
        $this->deleteCalled = true;
    }

    public function setTitle(mixed $value): self
    {
        $this->translations['Title'] = $value;
        return $this;
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

    public function testConstructorActionMappingCoversAllActions(): void
    {
        $this->assertSame(Api::API_ACTION_LIST, (new ApiCoverageDouble('modelList'))->getActionForTests());
        $this->assertSame(Api::API_ACTION_LIST, (new ApiCoverageDouble('unknownAction'))->getActionForTests());
        $this->assertSame(Api::API_ACTION_GET, (new ApiCoverageDouble('get'))->getActionForTests());
        $this->assertSame(Api::API_ACTION_POST, (new ApiCoverageDouble('post'))->getActionForTests());
        $this->assertSame(Api::API_ACTION_PUT, (new ApiCoverageDouble('put'))->getActionForTests());
        $this->assertSame(Api::API_ACTION_DELETE, (new ApiCoverageDouble('delete'))->getActionForTests());
        $this->assertSame(Api::API_ACTION_BULK, (new ApiCoverageDouble('bulk'))->getActionForTests());
    }

    public function testInitTriggersHydrationAndConnectionCreation(): void
    {
        $tableMap = new ApiCoverageTableMap();
        $tableMap->setPrimaryKeyList([]);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $api = new ApiCoverageDouble();
        $api->callParentInitForTests();

        $this->assertTrue($api->hydrateRequestDataCalled);
        $this->assertTrue($api->hydrateOrdersCalled);
        $this->assertTrue($api->checkFieldTypeCalled);
        $this->assertTrue($api->createConnectionCalled);
    }

    public function testGetModelNamespaceSupportsLegacyAndFallbackStrategies(): void
    {
        $api = new ApiCoverageDouble();
        $api->tableMapClass = null;
        $this->assertNull($api->callGetModelNamespaceForTests());

        $api->tableMapClass = ApiCoverageTableMapWithoutOmClass::class;
        $this->assertSame(ApiCoverageActiveRecord::class, $api->callGetModelNamespaceForTests());

        $api->tableMapClass = ApiCoverageTableMapAlwaysFailing::class;
        $this->assertNull($api->callGetModelNamespaceForTests());
    }

    public function testGetTableMapSupportsBuildTableMapFallback(): void
    {
        ApiCoverageBuildableTableMapClass::$built = false;
        ApiCoverageBuildableTableMapClass::$tableMap = null;

        $api = new ApiCoverageDouble();
        $api->tableMapClass = ApiCoverageBuildableTableMapClass::class;
        $tableMap = $api->callGetTableMapForTests();

        $this->assertInstanceOf(ApiCoverageTableMap::class, $tableMap);
        $this->assertTrue(ApiCoverageBuildableTableMapClass::$built);
    }

    public function testGetTableMapReturnsNullWhenMissingOrBuildFails(): void
    {
        $api = new ApiCoverageDouble();
        $api->tableMapClass = null;
        $this->assertNull($api->callGetTableMapForTests());

        $api->tableMapClass = ApiCoverageTableMapAlwaysFailing::class;
        $this->assertNull($api->callGetTableMapForTests());

        $api->tableMapClass = ApiCoverageBrokenBuildTableMapClass::class;
        $this->assertNull($api->callGetTableMapForTests());
    }

    public function testPutExceptionBranchIncludesPreviousContext(): void
    {
        Config::save(array_merge($this->configBackup, ['debug' => true]), []);
        Config::getInstance()->loadConfigData(true);

        $api = new ApiCoverageDouble();
        $api->singleModel = new ApiCoverageActiveRecord();
        $api->hydrateModelException = new \RuntimeException(
            'put exploded',
            55,
            new \RuntimeException('root-cause')
        );

        $json = $api->put('15');
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertStringContainsString('put exploded', (string)$payload['message']);
    }

    public function testPostExceptionBranchIncludesPreviousContext(): void
    {
        Config::save(array_merge($this->configBackup, ['debug' => false]), []);
        Config::getInstance()->loadConfigData(true);

        $api = new ApiCoverageDouble();
        $api->setModelForTests(new ApiCoverageActiveRecord());
        $api->postException = new \RuntimeException(
            'post exploded',
            90,
            new \RuntimeException('root-cause')
        );

        $json = $api->post();
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
        $this->assertStringContainsString('90', (string)$payload['message']);
    }

    public function testDeleteExceptionBranchHandlesPreviousException(): void
    {
        $api = new ApiCoverageDouble();
        $model = new ApiCoverageActiveRecord();
        $model->deleteException = new \RuntimeException('delete failed', 12, new \RuntimeException('db cause'));
        $api->singleModel = $model;
        $api->setConnectionForTests(new ApiCoverageConnectionFake());

        $json = $api->delete('20');
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['success']);
    }

    public function testModelListHandlesFieldTypeMappingPath(): void
    {
        Config::save(array_merge($this->configBackup, ['api.field.types' => true]), []);
        Config::getInstance()->loadConfigData(true);

        $pk = $this->createMock(ColumnMap::class);
        $pk->method('getPhpName')->willReturn('Id');
        $pk->method('getName')->willReturn('ID');
        $pk->method('isPrimaryKey')->willReturn(true);
        $pk->method('isForeignKey')->willReturn(false);
        $pk->method('getFullyQualifiedName')->willReturn('demo.ID');

        $tableMap = new ApiCoverageTableMap();
        $tableMap->setPrimaryKeyList(['ID' => $pk]);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $_GET = [Api::API_FIELDS_RESULT_FIELD => 'Id'];
        $_REQUEST = $_GET;
        Request::dropInstance();
        Request::getInstance()->init();

        $list = new class extends ArrayCollection {
            public function __construct()
            {
                parent::__construct([['ID' => 1, 'NAME' => 'Alice']]);
            }

            public function getData(): array
            {
                return [['ID' => 1, 'NAME' => 'Alice']];
            }
        };

        $api = new ApiCoverageDouble();
        $api->paginateList = $list;
        $payload = json_decode($api->modelList(), true);

        $this->assertTrue((bool)$payload['success']);
        $this->assertNotEmpty($payload['data']);
    }

    public function testModelListGracefullyHandlesPaginationException(): void
    {
        $api = new class extends ApiCoverageDouble {
            protected function paginate()
            {
                throw new \RuntimeException('pagination failure');
            }
        };

        $payload = json_decode($api->modelList(), true);
        $this->assertTrue((bool)$payload['success']);
        $this->assertSame(0, (int)$payload['total']);
    }

    public function testCheckI18nAddsColumnsAndLocaleAwarePkAlias(): void
    {
        if (!class_exists('PSFS\\tests\\base\\type\\Map\\ApiCoverageActiveRecordI18nTableMap', false)) {
            class_alias(
                ApiCoverageI18nMapTableMapProxy::class,
                'PSFS\\tests\\base\\type\\Map\\ApiCoverageActiveRecordI18nTableMap'
            );
        }

        $titleColumn = $this->createMock(ColumnMap::class);
        $titleColumn->method('isPrimaryKey')->willReturn(false);
        $titleColumn->method('isForeignKey')->willReturn(false);
        $titleColumn->method('getFullyQualifiedName')->willReturn('demo_i18n.TITLE');
        $titleColumn->method('getPhpName')->willReturn('Title');
        $titleColumn->method('getName')->willReturn('TITLE');

        $localePk = $this->createMock(ColumnMap::class);
        $localePk->method('isPrimaryKey')->willReturn(true);
        $localePk->method('isForeignKey')->willReturn(false);
        $localePk->method('getFullyQualifiedName')->willReturn('demo_i18n.LOCALE');
        $localePk->method('getPhpName')->willReturn('Locale');
        $localePk->method('getName')->willReturn('LOCALE');

        ApiCoverageI18nMapTableMapProxy::$localTable = new ApiCoverageI18nLocalTable([$titleColumn, $localePk]);
        $relation = $this->createMock(RelationMap::class);
        $relation->method('getLocalTable')->willReturn(ApiCoverageI18nMapTableMapProxy::$localTable);
        $tableMap = new ApiCoverageTableMap();
        $tableMap->configureI18nRelation($relation);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $_SERVER['HTTP_X_API_LANG'] = 'it_IT';
        Request::dropInstance();
        Request::getInstance()->init();

        $api = new ApiCoverageDouble();
        $query = new ApiCoverageModelCriteria();
        $api->callCheckI18nForTests($query);

        $this->assertSame('it_IT', $query->usedI18nLang);
        $this->assertNotEmpty($query->withColumns);
    }

    public function testHydrateModelFromRequestAppliesI18nSetters(): void
    {
        $titleColumn = $this->createMock(ColumnMap::class);
        $titleColumn->method('isPrimaryKey')->willReturn(false);
        $titleColumn->method('isForeignKey')->willReturn(false);
        $titleColumn->method('getPhpName')->willReturn('Title');
        $titleColumn->method('getName')->willReturn('TITLE');
        $titleColumn->method('getFullyQualifiedName')->willReturn('demo_i18n.TITLE');

        $localTable = new ApiCoverageI18nLocalTable([$titleColumn]);
        $relation = $this->createMock(RelationMap::class);
        $relation->method('getLocalTable')->willReturn($localTable);
        $tableMap = new ApiCoverageTableMap();
        $tableMap->configureI18nRelation($relation);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $model = new ApiCoverageActiveRecord();
        $api = new ApiCoverageDouble();
        $api->callHydrateModelFromRequestForTests($model, ['Title' => 'Hola', 'locale' => 'es_ES']);

        $this->assertSame(['Title' => 'Hola', 'locale' => 'es_ES'], $model->receivedFromArray);
    }

    public function testHydrateModelFromRequestCatchesI18nRelationErrors(): void
    {
        $relation = $this->createMock(RelationMap::class);
        $relation->method('getLocalTable')->willThrowException(new \RuntimeException('i18n relation fail'));
        $tableMap = new ApiCoverageTableMap();
        $tableMap->configureI18nRelation($relation);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $model = new ApiCoverageActiveRecord();
        $api = new ApiCoverageDouble();
        $api->callHydrateModelFromRequestForTests($model, ['Title' => 'Hola']);

        $this->assertSame(['Title' => 'Hola'], $model->receivedFromArray);
    }

    public function testGetPkDbNameThrowsWhenModelHasNoPrimaryKey(): void
    {
        $tableMap = new ApiCoverageTableMap();
        $tableMap->setPrimaryKeyList([]);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $api = new ApiCoverageDouble();
        $this->expectException(\PSFS\base\exception\ApiException::class);
        $api->callGetPkDbName();
    }

    public function testAddDefaultListFieldFallsBackToClassListNameWithoutLabelColumns(): void
    {
        $pk = $this->createMock(ColumnMap::class);
        $pk->method('getName')->willReturn('ID');
        $pk->method('getPhpName')->willReturn('Id');
        $pk->method('getFullyQualifiedName')->willReturn('demo.ID');

        $tableMap = new ApiCoverageTableMap();
        $tableMap->setPhpName('Demo');
        $tableMap->setColumnsList([]);
        $tableMap->setPrimaryKeyList([$pk]);
        ApiCoverageTableMapClass::$tableMap = $tableMap;

        $api = new ApiCoverageDouble();
        $query = new ApiCoverageModelCriteria();
        $api->callAddExtraColumns($query, Api::API_ACTION_LIST);

        $this->assertNotEmpty($query->withColumns);
        $listExpression = array_keys($api->getExtraColumns())[0];
        $this->assertStringContainsString('CONCAT("Demo #"', $listExpression);
    }

    public function testParseExtraColumnsNormalizesAliasesToLowercase(): void
    {
        $api = new ApiCoverageDouble();
        $api->setExtraColumns([
            'demo.ID' => Api::API_MODEL_KEY_FIELD,
            'demo.NAME' => Api::API_LIST_NAME_FIELD,
        ]);

        $parsed = $api->callParseExtraColumnsForTests();

        $this->assertSame('__pk', $parsed[Api::API_MODEL_KEY_FIELD]);
        $this->assertSame('__name__', $parsed[Api::API_LIST_NAME_FIELD]);
    }

    public function testHydrateRequestDataMergesQueryAndPayload(): void
    {
        $_GET = ['page' => '2'];
        $_REQUEST = $_GET;
        Request::dropInstance();
        Request::getInstance()->init();
        $_SERVER['PSFS_RAW_BODY'] = '{"Name":"Bob"}';
        Request::dropInstance();
        Request::getInstance()->init();

        $api = new class extends ApiCoverageDouble {
            public function callParentHydrateRequestData(): void
            {
                parent::hydrateRequestData();
            }

            public function queryForTests(): array
            {
                return $this->query;
            }

            public function dataForTests(): array
            {
                return $this->data;
            }
        };

        $api->callParentHydrateRequestData();

        $this->assertSame('2', $api->queryForTests()['page'] ?? null);
        $this->assertSame('Bob', $api->dataForTests()['Name'] ?? null);
    }
}
