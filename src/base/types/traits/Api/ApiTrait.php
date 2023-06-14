<?php
namespace PSFS\base\types\traits\Api;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;
use PSFS\base\config\Config;
use PSFS\base\exception\ApiException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\traits\JsonTrait;

/**
 * Trait ApiCrudTrait
 * @package PSFS\base\types\traits
 */
trait ApiTrait {
    use ConnectionTrait;
    use MutationTrait;
    use JsonTrait {
        JsonTrait::json as _json;
    }

    /**
     * @var \Propel\Runtime\ActiveRecord\ActiveRecordInterface $model
     */
    protected $model;

    /**
     * @var string
     */
    protected $action;

    /**
     * Method that extract the Api name
     * @return mixed
     */
    public function getApi()
    {
        $model = explode("\\", $this->getModelNamespace());

        return $model[count($model) - 1];
    }

    /**
     * Method that extract the Domain name
     * @return mixed
     */
    public function getDomain()
    {
        $model = explode("\\", $this->getModelNamespace());
        return strlen($model[0]) || 1 === count($model) ? $model[0] : $model[1];
    }

    /**
     * Returns active model
     * @return \Propel\Runtime\ActiveRecord\ActiveRecordInterface
     */
    protected function getModel()
    {
        return $this->model;
    }

    /**
     * Returns an array conversion of Api Model
     * @return array
     */
    protected function renderModel()
    {
        return (NULL !== $this->model) ? $this->model->toArray() : [];
    }

    protected function extractFields() { }

    /**
     * Hydrate fields from request
     */
    protected function hydrateFromRequest()
    {
        $class = new \ReflectionClass($this->getModelNamespace());
        $this->model = $class->newInstance();
        $this->hydrateModelFromRequest($this->model, $this->data);
    }

    /**
     * Hydrate list elements for bulk insert
     */
    protected function hydrateBulkRequest() {
        $class = new \ReflectionClass($this->getModelNamespace());
        $this->list = [];
        foreach($this->data as $item) {
            if(is_array($item)) {
                if(count($this->list) < Config::getParam('api.block.limit', 1000)) {
                    /** @var ActiveRecordInterface $model */
                    $model = $class->newInstance();
                    $this->hydrateModelFromRequest($model, $item);
                    $this->list[] = $model;
                } else {
                    Logger::log(t('Max items per bulk insert raised'), LOG_WARNING, count($this->data) . t('items'));
                }
            }
        }
    }

    /**
     * Save the list of items
     */
    protected function saveBulk() {
        $tablemap = $this->getTableMap();
        foreach($this->list as &$model) {
            $con = Propel::getWriteConnection($tablemap::DATABASE_NAME);
            try {
                $model->save($con);
                $con->commit();
            } catch(\Exception $e) {
                Logger::log($e->getMessage(), LOG_ERR, $model->toArray());
                $con->rollBack();
            }
        }
    }

    /**
     * @return array
     */
    protected function exportList() {
        $list = [];
        /** @var ActiveRecordInterface $item */
        foreach($this->list as $item) {
            $list[] = $item->toArray();
        }
        return $list;
    }

    /**
     * Method that allow joins between models
     *
     * @param ModelCriteria $query
     */
    protected function joinTables(ModelCriteria &$query)
    {
        //TODO for specific implementations
    }

    /**
     * @param ModelCriteria $query
     * @param string $primaryKey
     * @return ActiveRecordInterface|null
     * @throws ApiException
     */
    protected function findPk(ModelCriteria $query, $primaryKey) {
        $pks = explode(Api::API_PK_SEPARATOR, urldecode($primaryKey));
        if(count($pks) === 1 && !empty($pks[0])) {
            $query->filterByPrimaryKey($pks[0]);
        } else {
            $item = 0;
            foreach($this->getPkDbName() as $phpName) {
                try {
                    $query->filterBy($phpName, $pks[$item]);
                    $item++;
                    if($item >= count($pks)) {
                        break;
                    }
                } catch(\Exception $e) {
                    Logger::log($e->getMessage(), LOG_DEBUG);
                }
            }
        }
        $results = $query->find($this->con);
        return $results->getFirst();
    }

    /**
     * @return ModelCriteria
     */
    protected function prepareQuery() {
        $query = ApiHelper::extractQuery($this->getModelNamespace(), $this->con);
        $this->joinTables($query);
        $this->checkI18n($query);
        $this->addExtraColumns($query, $this->action);
        return $query;
    }

    /**
     * Hydrate model from pk
     *
     * @param string $primaryKey
     */
    protected function hydrateModel($primaryKey)
    {
        try {
            $query = $this->prepareQuery();
            $this->model = $this->findPk($query, $primaryKey);
        } catch (\Exception $e) {
            Logger::log(get_class($this) . ': ' . $e->getMessage(), LOG_ERR);
        }
    }

    /**
     * Extract specific entity
     *
     * @param integer $primaryKey
     *
     * @return null|ActiveRecordInterface
     */
    protected function _get($primaryKey)
    {
        $this->hydrateModel($primaryKey);

        return ($this->getModel() instanceof ActiveRecordInterface) ? $this->getModel() : NULL;
    }

    /**
     * @param ModelCriteria $query
     */
    protected function checkReturnFields(ModelCriteria &$query)
    {
        $returnFields = Request::getInstance()->getQuery(Api::API_FIELDS_RESULT_FIELD);
        if (null !== $returnFields) {
            $fields = explode(',', $returnFields);
            $select = [];
            /** @var TableMap $tablemap */
            $tablemap = $this->getTableMap();
            foreach ($fields as $field) {
                if (in_array($field, $this->extraColumns)) {
                    $select[] = $field;
                } elseif (null !== ApiHelper::checkFieldExists($tablemap, $field)) {
                    $select[] = $field;
                }
            }
            if (count($select) > 0) {
                $query->select($select);
            }
        }
    }

    /**
     * Wrapper for json parent method with close transactions and close connections tasks
     *
     * @param \PSFS\base\dto\JsonResponse $response
     * @param int $status
     *
     */
    public function json($response, $status = 200)
    {
        $this->closeTransaction($status);
        return $this->_json($response, $status);
    }
}