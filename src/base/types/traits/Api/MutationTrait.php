<?php

namespace PSFS\base\types\traits\Api;

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

/**
 * Trait MutationTrait
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
    protected $lang;



    /**
     * @var string
     * @header X-FIELD-TYPE
     * @label Field type for API Dto
     * @default phpName
     */
    protected $fieldType = TableMap::TYPE_PHPNAME;

    /**
     * @var array extraColumns
     */
    protected $extraColumns = array();

    /**
     * Extract Model TableMap
     * @return TableMap
     */
    abstract function getModelTableMap();

    /**
     * Extract model api namespace
     * @return mixed
     */
    protected function getModelNamespace()
    {
        /** @var TableMap $tableMap */
        $tableMap = $this->getModelTableMap();
        return (null !== $tableMap) ? $tableMap::getOMClass(FALSE) : null;
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
        /** @var TableMap $tableMap */
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
        throw new ApiException(t('El modelo de la API no está debidamente mapeado, no hay Primary Key o es compuesta'));
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

    /**
     * Method that add a new field with the Label of the row
     */
    protected function addDefaultListField()
    {
        if (!in_array(Api::API_LIST_NAME_FIELD, array_values($this->extraColumns))) {
            /** @var TableMap $tableMap */
            $tableMap = $this->getTableMap();
            /** @var ColumnMap $column */
            $column = null;
            if ($tableMap->hasColumn('NAME')) {
                $column = $tableMap->getColumn('NAME');
            } elseif ($tableMap->hasColumn('TITLE')) {
                $column = $tableMap->getColumn('TITLE');
            } elseif ($tableMap->hasColumn('LABEL')) {
                $column = $tableMap->getColumn('LABEL');
            }
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
            $this->addDefaultListField();
            $this->addPkToList();
        }
        if (!empty($this->extraColumns)) {
            if(Config::getParam('api.extrafields.compat', true)) {
                $fields = array_values($this->extraColumns);
            } else {
                $returnFields = Request::getInstance()->getQuery(Api::API_FIELDS_RESULT_FIELD);
                $fields = explode(',', $returnFields ?: '');
                $fields[] = self::API_MODEL_KEY_FIELD;
            }
            foreach ($this->extraColumns as $expression => $columnName) {
                if(empty($fields) || in_array($columnName, $fields)) {
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

    protected function extractApiLang() {
        $defaultLanguage = explode('_', Config::getParam('default.language', 'es_ES'));
        $this->lang = Request::header(APi::HEADER_API_LANG, $defaultLanguage[0]);
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
            $i18nMapClass = str_replace('\\Models\\', '\\Models\\Map\\', $modelI18n) . 'TableMap';
            /** @var TableMap $modelI18nTableMap */
            $modelI18nTableMap = $i18nMapClass::getTableMap();
            foreach($modelI18nTableMap->getColumns() as $columnMap) {
                if(!$columnMap->isPrimaryKey()) {
                    $query->withColumn($columnMap->getFullyQualifiedName(), ApiHelper::getColumnMapName($columnMap));
                } elseif(!$columnMap->isForeignKey()) {
                    $query->withColumn('IFNULL(' . $columnMap->getFullyQualifiedName() . ', "'.$this->lang.'")', ApiHelper::getColumnMapName($columnMap));
                }
            }
        }
    }

    /**
     * @param ActiveRecordInterface $model
     * @param array $data
     */
    protected function hydrateModelFromRequest(ActiveRecordInterface $model, array $data = []) {
        $model->fromArray($data, ApiHelper::getFieldTypes());
        $tableMap = $this->getTableMap();
        try {
            if($tableMap->hasRelation($tableMap->getPhpName() . 'I18n'))
            {
                $relateI18n = $tableMap->getRelation($tableMap->getPhpName() . 'I18n');
                $i18NTableMap = $relateI18n->getLocalTable();
                $model->setLocale($this->lang);
                foreach($i18NTableMap->getColumns() as $columnMap) {
                    $method = 'set' . $columnMap->getPhpName();
                    $dtoColumnName = ApiHelper::getColumnMapName($columnMap);
                    if(array_key_exists($dtoColumnName, $data)
                        && method_exists($model, $method)
                        && !($columnMap->isPrimaryKey() && $columnMap->isForeignKey())) {
                        $model->$method($data[$dtoColumnName]);
                    }
                }
            }
        } catch(\Exception $e) {
            Logger::log($e->getMessage(), LOG_DEBUG);
        }
    }

    /**
     * Check and change the fieldType for API dto
     */
    protected function checkFieldType() {
        $this->fieldType = ApiHelper::getFieldTypes();
    }
}