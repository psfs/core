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
        return (null !== $tableMap) ? $tableMap::getOMClass(false) : null;
    }

    /**
     * @return TableMap
     */
    private function getTableMap()
    {
        $tableMapClass = $this->getModelTableMap();
        return (null !== $tableMapClass) ? $tableMapClass::getTableMap() : null;
    }

    /**
     * @return array
     * @throws ApiException
     */
    protected function getPkDbName()
    {
        $tableMap = $this->getTableMap();
        $pks = $tableMap->getPrimaryKeys();
        if (count($pks) === 1) {
            $pks = array_keys($pks);
            return [
                $tableMap::TABLE_NAME . '.' . $pks[0] => Api::API_MODEL_KEY_FIELD
            ];
        }
        if (count($pks) > 1) {
            $apiPks = [];
            $principal = '';
            $sep = 'CONCAT(';
            foreach ($pks as $pk) {
                $apiPks[$tableMap::TABLE_NAME . '.' . $pk->getName()] = $pk->getPhpName();
                $principal .= $sep . $tableMap::TABLE_NAME . '.' . $pk->getName();
                $sep = ', "' . Api::API_PK_SEPARATOR . '", ';
            }
            $principal .= ')';
            $apiPks[$principal] = Api::API_MODEL_KEY_FIELD;
            return $apiPks;
        }
        throw new ApiException(t('The API model is not properly mapped, there is no Primary Key or it is composite'));
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
        $pks = '';
        $sep = '';
        foreach ($tableMap->getPrimaryKeys() as $pk) {
            $pks .= $sep . $pk->getFullyQualifiedName();
            $sep = ', "|", ';
        }
        $this->extraColumns['CONCAT("' . $tableMap->getPhpName() . ' #", ' . $pks . ')'] = Api::API_LIST_NAME_FIELD;
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
        if (Api::API_ACTION_LIST === $action) {
            // Legacy tokens kept for compatibility (`__name__`, `__pk`).
            // Planned future cleanup can remove them behind a versioned contract switch.
            $this->addDefaultListField();
            $this->addPkToList();
        }
        if (!empty($this->extraColumns)) {
            $fields = $this->resolveRequestedExtraFields();
            foreach ($this->extraColumns as $expression => $columnName) {
                if (empty($fields) || in_array($columnName, $fields, true)) {
                    $query->withColumn($expression, $columnName);
                }
            }
        }
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
        $model = $this->getModelNamespace();
        $modelI18n = $model . 'I18n';
        if (method_exists($query, 'useI18nQuery')) {
            $query->useI18nQuery($this->lang);
            $modelParts = explode('\\', $modelI18n);
            $i18nMapClass = str_replace(end($modelParts), 'Map\\' . end($modelParts), $modelI18n) . 'TableMap';

            $modelI18nTableMap = $i18nMapClass::getTableMap();
            foreach ($modelI18nTableMap->getColumns() as $columnMap) {
                if (!$columnMap->isPrimaryKey()) {
                    $query->withColumn($columnMap->getFullyQualifiedName(), ApiHelper::getColumnMapName($columnMap));
                } elseif (!$columnMap->isForeignKey()) {
                    $query->withColumn(
                        'IFNULL(' . $columnMap->getFullyQualifiedName() . ', "' . $this->lang . '")',
                        ApiHelper::getColumnMapName($columnMap)
                    );
                }
            }
        }
    }

    protected function cleanData(array &$data)
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->cleanData($value);
            } else {
                if (is_string($value)) {
                    $value = $this->sanitizeString($value);
                }
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
        try {
            if ($tableMap->hasRelation($tableMap->getPhpName() . 'I18n')) {
                $relateI18n = $tableMap->getRelation($tableMap->getPhpName() . 'I18n');
                $i18NTableMap = $relateI18n->getLocalTable();
                $model->setLocale($this->resolveLocaleFromInput($data));
                foreach ($i18NTableMap->getColumns() as $columnMap) {
                    $method = 'set' . $columnMap->getPhpName();
                    $dtoColumnName = ApiHelper::getColumnMapName($columnMap);
                    if (array_key_exists($dtoColumnName, $data)
                        && method_exists($model, $method)
                        && !($columnMap->isPrimaryKey() && $columnMap->isForeignKey())) {
                        $model->$method($data[$dtoColumnName]);
                    }
                }
            }
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
        $returnFields = Request::getInstance()->getQuery(Api::API_FIELDS_RESULT_FIELD);
        $fields = explode(',', $returnFields ?: '');
        $fields[] = self::API_MODEL_KEY_FIELD;
        return $fields;
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
