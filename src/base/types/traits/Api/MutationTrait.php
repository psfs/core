<?php

namespace PSFS\base\types\traits\Api;

use Exception;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\attributes\DefaultValue;
use PSFS\base\types\helpers\attributes\Header;
use PSFS\base\types\helpers\attributes\Label;

/**
 * @package PSFS\base\types\traits\Api
 */
trait MutationTrait
{
    use MutationI18nTrait;
    /**
     * @var string
     * @header X-API-LANG
     * @label Locale for the API request
     * @default es
     */
    #[Header(Api::HEADER_API_LANG)]
    #[Label('Locale for the API request')]
    #[DefaultValue('es')]
    protected $lang;

    /**
     * @var string
     * @header X-FIELD-TYPE
     * @label Field type for API Dto
     * @default phpName
     */
    #[Header(Api::HEADER_API_FIELDTYPE)]
    #[Label('Field type for API Dto')]
    #[DefaultValue('phpName')]
    protected $fieldType = TableMap::TYPE_PHPNAME;

    /**
     * @var array
     */
    protected $extraColumns = array();

    /**
     * @var array
     */
    protected $query = array();

    /**
     * @var array
     */
    protected $data = array();

    /**
     * Number of items persisted successfully in a bulk save operation.
     *
     * @var int
     */
    protected int $bulkSavedCount = 0;

    /**
     * @return TableMap
     */
    abstract function getModelTableMap();


    protected function hydrateRequestData()
    {
        $request = Request::getInstance();
        $this->query = array_merge($this->query, $request->getQueryParams());
        $this->data = array_merge($this->data, $request->getRawData());
    }

    /**
     * @return mixed
     */
    protected function getModelNamespace()
    {
        $tableMap = $this->getModelTableMap();
        if (null === $tableMap) {
            return null;
        }

        if (method_exists($tableMap, 'getOMClass')) {
            try {
                $legacyClassName = $tableMap::getOMClass(false);
                if (is_string($legacyClassName) && $legacyClassName !== '') {
                    return $legacyClassName;
                }
            } catch (\Throwable) {
                // Fall back to table map introspection below.
            }
        }
        try {
            $map = $tableMap::getTableMap();
            $className = $map?->getClassName();
            if (is_string($className) && $className !== '') {
                return $className;
            }
        } catch (\Throwable) {
            // Keep null when table map cannot be resolved in early runtime.
        }
        return null;
    }

    /**
     * @return TableMap
     */
    private function getTableMap()
    {
        $tableMapClass = $this->getModelTableMap();
        if (null === $tableMapClass) {
            return null;
        }
        try {
            return $tableMapClass::getTableMap();
        } catch (\Throwable) {
            if (method_exists($tableMapClass, 'buildTableMap')) {
                try {
                    $tableMapClass::buildTableMap();
                    return $tableMapClass::getTableMap();
                } catch (\Throwable) {
                    return null;
                }
            }
            return null;
        }
    }

    /**
     * @return array
     * @throws ApiException
     */
    protected function getPkDbName()
    {
        $tableMap = $this->requireTableMapForPrimaryKeys();
        $tableName = $this->resolveTableName($tableMap);
        $pks = $tableMap->getPrimaryKeys();
        $pkCount = count($pks);
        if ($pkCount === 1) {
            return $this->buildSinglePkMap($tableName, $pks);
        }
        if ($pkCount > 1) {
            return $this->buildCompositePkMap($tableName, $pks);
        }
        throw new ApiException(t('The API model is not properly mapped, there is no Primary Key or it is composite'));
    }

    /**
     * @throws ApiException
     */
    private function requireTableMapForPrimaryKeys(): TableMap
    {
        $tableMap = $this->getTableMap();
        if (!$tableMap instanceof TableMap) {
            throw new ApiException(t('The API model is not properly mapped, there is no Primary Key or it is composite'));
        }
        return $tableMap;
    }

    private function resolveTableName(TableMap $tableMap): string
    {
        $tableMapClass = get_class($tableMap);
        $tableName = (defined($tableMapClass . '::TABLE_NAME'))
            ? (string)constant($tableMapClass . '::TABLE_NAME')
            : '';
        if ($tableName === '' && method_exists($tableMap, 'getName')) {
            $tableName = (string)$tableMap->getName();
        }
        if ($tableName === '' && method_exists($tableMap, 'getPhpName')) {
            $tableName = (string)$tableMap->getPhpName();
        }
        return $tableName;
    }

    /**
     * @param array<int, ColumnMap> $primaryKeys
     * @return array<string, string>
     */
    private function buildSinglePkMap(string $tableName, array $primaryKeys): array
    {
        $pkKeys = array_keys($primaryKeys);
        $pkName = (string)($pkKeys[0] ?? '');

        return [
            $tableName . '.' . $pkName => Api::API_MODEL_KEY_FIELD,
        ];
    }

    /**
     * @param array<int, ColumnMap> $primaryKeys
     * @return array<string, string>
     */
    private function buildCompositePkMap(string $tableName, array $primaryKeys): array
    {
        $apiPks = [];
        $segments = [];
        foreach ($primaryKeys as $pk) {
            $apiPks[$tableName . '.' . $pk->getName()] = $pk->getPhpName();
            $segments[] = $tableName . '.' . $pk->getName();
        }
        $principal = $this->buildCompositePkExpression($segments);
        $apiPks[$principal] = Api::API_MODEL_KEY_FIELD;
        return $apiPks;
    }

    /**
     * @param array<int, string> $segments
     */
    private function buildCompositePkExpression(array $segments): string
    {
        if (count($segments) === 0) {
            return 'CONCAT()';
        }

        $glue = ', "' . Api::API_PK_SEPARATOR . '", ';
        return 'CONCAT(' . implode($glue, $segments) . ')';
    }

    /**
     * @throws ApiException
     */
    protected function addPkToList()
    {
        foreach ($this->getPkDbName() as $extraColumn => $columnName) {
            $this->extraColumns[$extraColumn] = $columnName;
        }
    }

    /**
     * @param TableMap $tableMap
     */
    private function addClassListName(TableMap $tableMap)
    {
        $segments = [];
        foreach ($tableMap->getPrimaryKeys() as $pk) {
            $segments[] = $pk->getFullyQualifiedName();
        }

        $this->extraColumns[$this->buildClassListNameExpression((string)$tableMap->getPhpName(), $segments)] = Api::API_LIST_NAME_FIELD;
    }

    /**
     * @param array<int, string> $segments
     */
    private function buildClassListNameExpression(string $phpName, array $segments): string
    {
        if (count($segments) === 0) {
            return 'CONCAT("' . $phpName . ' #")';
        }

        return 'CONCAT("' . $phpName . ' #", ' . implode(', "|", ', $segments) . ')';
    }


    protected function addDefaultListField()
    {
        if (!in_array(Api::API_LIST_NAME_FIELD, array_values($this->extraColumns), true)) {
            $tableMap = $this->getTableMap();
            $column = $this->resolveDefaultListColumn($tableMap);
            if (null !== $column) {
                $this->extraColumns[$column->getFullyQualifiedName()] = Api::API_LIST_NAME_FIELD;
            } else {
                $this->addClassListName($tableMap);
            }
        }
    }

    /**
     * @param ModelCriteria $query
     * @param $action
     * @throws ApiException
     */
    private function addExtraColumns(ModelCriteria &$query, $action)
    {
        $this->prepareLegacyListExtraColumns((string)$action);
        if (empty($this->extraColumns)) {
            return;
        }

        $fields = $this->resolveRequestedExtraFields();
        foreach ($this->extraColumns as $expression => $columnName) {
            if ($this->shouldIncludeExtraColumn($columnName, $fields)) {
                $query->withColumn($expression, $columnName);
            }
        }
    }

    private function prepareLegacyListExtraColumns(string $action): void
    {
        if (Api::API_ACTION_LIST !== $action) {
            return;
        }
        // Legacy tokens kept for compatibility (`__name__`, `__pk`).
        // Planned future cleanup can remove them behind a versioned contract switch.
        $this->addDefaultListField();
        $this->addPkToList();
    }

    /**
     * @param array<int, string> $fields
     */
    private function shouldIncludeExtraColumn(string $columnName, array $fields): bool
    {
        return empty($fields) || in_array($columnName, $fields, true);
    }

    /**
     * @return array
     */
    protected function parseExtraColumns()
    {
        $columns = [];
        foreach ($this->extraColumns as $columnName) {
            $columns[$columnName] = strtolower($columnName);
        }
        return $columns;
    }

    protected function extractApiLang()
    {
        $defaultLanguage = (string)Config::getParam('default.language', 'en_US');
        $this->lang = Request::header(Api::HEADER_API_LANG, $defaultLanguage);
    }

    /**
     * @param ModelCriteria $query
     */
    protected function checkI18n(ModelCriteria &$query)
    {
        $this->extractApiLang();
        if (!$this->hasI18nQuerySupport($query)) {
            return;
        }
        $query->useI18nQuery($this->lang);
        $model = (string)$this->getModelNamespace();
        $i18nMapClass = $this->resolveI18nMapClassName($model);
        $modelI18nTableMap = $i18nMapClass::getTableMap();
        $this->appendI18nColumnsToQuery($query, $modelI18nTableMap, (string)$this->lang);
    }

    protected function cleanData(array &$data)
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->cleanData($value);
                continue;
            }
            if (is_string($value)) {
                $value = $this->sanitizeString($value);
            }
        }
    }

    /**
     * @param ActiveRecordInterface $model
     * @param array $data
     */
    protected function hydrateModelFromRequest(ActiveRecordInterface $model, array $data = [])
    {
        $this->cleanData($data);
        $model->fromArray($data, ApiHelper::getFieldTypes());
        $tableMap = $this->getTableMap();
        if (!$tableMap instanceof TableMap) {
            return;
        }
        try {
            $this->applyI18nFieldsToModel($model, $tableMap, $data, $this->resolveLocaleFromInput($data));
        } catch (Exception $e) {
            Logger::log($e->getMessage(), LOG_DEBUG);
        }
    }


    protected function checkFieldType()
    {
        $this->fieldType = ApiHelper::getFieldTypes();
    }

    protected function getBulkSavedCount(): int
    {
        return $this->bulkSavedCount;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveRequestedExtraFields(): array
    {
        if (Config::getParam('api.extrafields.compat', true)) {
            return array_values($this->extraColumns);
        }
        return $this->normalizeRequestedExtraFields(
            (string)(Request::getInstance()->getQuery(Api::API_FIELDS_RESULT_FIELD) ?? '')
        );
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRequestedExtraFields(string $returnFields): array
    {
        $fields = explode(',', $returnFields);
        $fields[] = self::API_MODEL_KEY_FIELD;
        $fields = array_values(array_filter(array_map('trim', $fields), static fn(string $field): bool => $field !== ''));

        return array_values(array_unique($fields));
    }

    protected function sanitizeString(string $value): string
    {
        return I18nHelper::cleanHtmlAttacks($value);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function resolveLocaleFromInput(array $data): string
    {
        if (array_key_exists('Locale', $data)) {
            return (string)$data['Locale'];
        }
        if (array_key_exists('locale', $data)) {
            return (string)$data['locale'];
        }
        $defaultLanguage = (string)Config::getParam('default.language', 'en_US');
        return (string)Request::header(Api::HEADER_API_LANG, $defaultLanguage);
    }

    private function resolveDefaultListColumn(TableMap $tableMap): ?ColumnMap
    {
        foreach (['NAME', 'TITLE', 'LABEL'] as $columnName) {
            if ($tableMap->hasColumn($columnName)) {
                return $tableMap->getColumn($columnName);
            }
        }
        return null;
    }
}
