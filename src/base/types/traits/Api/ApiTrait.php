<?php
namespace PSFS\base\types\traits\Api;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
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
        json as _json;
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
     * @var array $data
     */
    protected $data = array();

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
        return (strlen($model[0])) ? $model[0] : $model[1];
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
     * @param string $pk
     * @return ActiveRecordInterface|null
     * @throws ApiException
     */
    protected function findPk(ModelCriteria $query, $pk) {
        $pks = explode(Api::API_PK_SEPARATOR, urldecode($pk));
        if(count($pks) == 1 && !empty($pks[0])) {
            $model = $query->findPk($pks[0], $this->con);
        } else {
            $i = 0;
            foreach($this->getPkDbName() as $key => $phpName) {
                try {
                    $query->filterBy($phpName, $pks[$i]);
                    $i++;
                    if($i >= count($pks)) break;
                } catch(\Exception $e) {
                    Logger::log($e->getMessage(), LOG_DEBUG);
                }
            }
            $model = $query->findOne($this->con);
        }
        return $model;
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
     * @param string $pk
     */
    protected function hydrateModel($pk)
    {
        try {
            $query = $this->prepareQuery();
            $this->model = $this->findPk($query, $pk);
        } catch (\Exception $e) {
            Logger::log(get_class($this) . ': ' . $e->getMessage(), LOG_ERR);
        }
    }

    /**
     * Extract specific entity
     *
     * @param integer $pk
     *
     * @return null|ActiveRecordInterface
     */
    protected function _get($pk)
    {
        $this->hydrateModel($pk);

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
                } elseif ($tablemap->hasColumnByPhpName($field)) {
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
     * @return string
     */
    public function json($response, $status = 200)
    {
        $this->closeTransaction($status);
        return $this->_json($response, $status);
    }
}