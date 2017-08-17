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

/**
 * Trait MutationTrait
 * @package PSFS\base\types\traits\Api
 */
trait MutationTrait
{
    /**
     * @var string
     * @header X-API-LANG
     * @label Idioma de la petición REST
     * @default es
     */
    protected $lang;

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
    private function getModelNamespace()
    {
        /** @var TableMap $tableMap */
        $tableMap = $this->getModelTableMap();
        return $tableMap::getOMClass(FALSE);
    }

    /**
     * @return TableMap
     */
    private function getTableMap()
    {
        $tableMapClass = $this->getModelTableMap();
        return $tableMapClass::getTableMap();
    }

    protected function getPkDbName()
    {
        /** @var TableMap $tableMap */
        $tableMap = $this->getTableMap();
        $pks = $tableMap->getPrimaryKeys();
        if (count($pks) == 1) {
            $pks = array_keys($pks);
            return [
                $tableMap::TABLE_NAME . '.' . $pks[0] => Api::API_MODEL_KEY_FIELD
            ];
        } elseif (count($pks) > 1) {
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
        } else {
            throw new ApiException(_('El modelo de la API no está debidamente mapeado, no hay Primary Key o es compuesta'));
        }
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
     * Add extra columns to pagination query
     *
     * @param ModelCriteria $query
     * @param string $action
     */
    private function addExtraColumns(ModelCriteria &$query, $action)
    {
        if (Api::API_ACTION_LIST === $action) {
            $this->addDefaultListField();
            $this->addPkToList();
        }
        if (!empty($this->extraColumns)) {
            foreach ($this->extraColumns as $expression => $columnName) {
                $query->withColumn($expression, $columnName);
            }
        }
    }

    /**
     * @return array
     */
    protected function parseExtraColumns()
    {
        $columns = [];
        foreach ($this->extraColumns as $key => $columnName) {
            $columns[$columnName] = strtolower($columnName);
        }
        return $columns;
    }


    protected function extractApiLang() {
        $default_language = explode('_', Config::getParam('default.language', 'es_ES'));
        $this->lang = Request::header(APi::HEADER_API_LANG, $default_language[0]);
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
            $modelI18nTableMapClass = str_replace('\\Models\\', '\\Models\\Map\\', $modelI18n) . 'TableMap';
            /** @var TableMap $modelI18nTableMap */
            $modelI18nTableMap = $modelI18nTableMapClass::getTableMap();
            foreach($modelI18nTableMap->getColumns() as $columnMap) {
                if(!$columnMap->isPrimaryKey()) {
                    $query->withColumn($modelI18nTableMapClass::TABLE_NAME . '.' . $columnMap->getName(), $columnMap->getPhpName());
                } elseif(!$columnMap->isForeignKey()) {
                    $query->withColumn('IFNULL(' . $modelI18nTableMapClass::TABLE_NAME . '.' . $columnMap->getName() . ', "'.$this->lang.'")', $columnMap->getPhpName());
                }
            }
        }
    }

    /**
     * @param ActiveRecordInterface $model
     * @param array $data
     */
    protected function hydrateModelFromRequest(ActiveRecordInterface &$model, array $data = []) {
        $model->fromArray($data);
        $tableMap = $this->getTableMap();
        try {
            $relateI18n = $tableMap->getRelation($tableMap->getPhpName() . 'I18n');
            if(null !== $relateI18n) {
                $i18NTableMap = $relateI18n->getLocalTable();
                foreach($i18NTableMap->getColumns() as $columnMap) {
                    $method = 'set' . $columnMap->getPhpName();
                    if(!($columnMap->isPrimaryKey() && $columnMap->isForeignKey())
                        &&array_key_exists($columnMap->getPhpName(), $data)
                        && method_exists($model, $method)) {
                        $model->$method($data[$columnMap->getPhpName()]);
                    }
                }
            }
        } catch(\Exception $e) {
            Logger::log($e->getMessage(), LOG_WARNING);
        }
    }
}