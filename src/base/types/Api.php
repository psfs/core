<?php
namespace PSFS\base\types;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;
use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\dto\Order;
use PSFS\base\exception\ApiException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\ApiFormHelper;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\traits\JsonTrait;
use PSFS\base\types\traits\RouteTrait;

/**
 * Class Api
 * @package PSFS\base
 */
abstract class Api extends Singleton
{
    use JsonTrait {
        json as _json;
    }
    use RouteTrait;

    const API_COMBO_FIELD = '__combo';
    const API_LIST_NAME_FIELD = '__name__';
    const API_FIELDS_RESULT_FIELD = '__fields';
    const API_MODEL_KEY_FIELD = '__pk';

    const API_ACTION_LIST = 'list';
    const API_ACTION_GET = 'read';
    const API_ACTION_POST = 'create';
    const API_ACTION_PUT = 'update';
    const API_ACTION_DELETE = 'delete';

    const HEADER_API_TOKEN = 'X-API-SEC-TOKEN';

    /**
     * @var \Propel\Runtime\ActiveRecord\ActiveRecordInterface $model
     */
    protected $model;

    /**
     * @var \Propel\Runtime\Collection\Collection|\Propel\Runtime\Util\PropelModelPager $list
     */
    protected $list;

    /**
     * @var array $filters
     */
    protected $filters = array();

    /**
     * @Inyectable
     * @var \PSFS\base\dto\Order $order
     */
    protected $order;

    /**
     * @var array $query
     */
    protected $query = array();

    /**
     * @var array $data
     */
    protected $data = array();

    /**
     * @var ConnectionInterface con
     */
    protected $con = null;

    /**
     * @var array extraColumns
     */
    protected $extraColumns = array();
    /**
     * @var string
     */
    protected $action;

    /**
     * Initialize api
     */
    public function init()
    {
        parent::init();
        $this->domain = $this->getApi();
        $this->debug = Config::getInstance()->getDebugMode() || Config::getInstance()->get('debugQueries');
        $this->hydrateRequestData();
        $this->hydrateOrders();
        $this->createConnection();
    }

    /**
     * Wrapper de asignación de los menus
     * @return array
     */
    protected function getMenu()
    {
        return Router::getInstance()->getAdminRoutes();
    }

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
        $tableMap = $this->getModelTableMap();
        return $tableMap::getOMClass(FALSE);
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
        return (NULL !== $this->model) ? $this->model->toArray() : array();
    }

    /**
     * Hydrate order from request
     */
    private function hydrateOrders()
    {
        if (count($this->query)) {
            foreach ($this->query as $key => $value) {
                if ($key === '__order') {
                    foreach ($value as $field => $direction) {
                        $this->order->addOrder($field, $direction);
                    }
                }
            }
        }
    }

    /**
     * Extract pagination values
     * @return array
     */
    private function extractPagination()
    {
        $page = (array_key_exists('__page', $this->query)) ? $this->query['__page'] : 1;
        $limit = (array_key_exists('__limit', $this->query)) ? $this->query['__limit'] : 100;

        return array($page, $limit);
    }

    /**
     * @return TableMap
     */
    private function getTableMap()
    {
        $tableMapClass = $this->getModelTableMap();
        return $tableMapClass::getTableMap();
    }

    /**
     * Add order fields to query
     *
     * @param ModelCriteria $query
     */
    private function addOrders(ModelCriteria &$query)
    {
        $orderAdded = FALSE;
        $tableMap = $this->getTableMap();
        foreach ($this->order->getOrders() as $field => $direction) {
            if ($column = ApiHelper::checkFieldExists($tableMap, $field)) {
                $orderAdded = TRUE;
                if ($direction === Order::ASC) {
                    $query->addAscendingOrderByColumn($column->getPhpName());
                } else {
                    $query->addDescendingOrderByColumn($column->getPhpName());
                }
            }
        }
        if (!$orderAdded) {
            $query->addAscendingOrderByColumn($this->getPkDbName());
        }
    }

    private function getPkDbName() {
        $tableMap = $this->getTableMap();
        $pks = $tableMap->getPrimaryKeys();
        if (count($pks) == 1) {
            $pks = array_keys($pks);
            return $tableMap::TABLE_NAME . '.' . $pks[0];
        } else {
            throw new ApiException(_('El modelo de la API no está debidamente mapeado, no hay Primary Key o es compuesta'));
        }
    }

    /**
     * @throws ApiException
     */
    private function addPkToList()
    {
        $pkName = $this->getPkDbName();
        $this->extraColumns[$pkName] = self::API_MODEL_KEY_FIELD;
    }

    /**
     * Method that add a new field with the Label of the row
     */
    private function addDefaultListField()
    {
        if (!in_array(self::API_LIST_NAME_FIELD, $this->extraColumns)) {
            $tableMap = $this->getTableMap();
            $column = null;
            if ($tableMap->hasColumn('NAME')) {
                $column = $tableMap->getColumn('NAME');
            } elseif ($tableMap->hasColumn('TITLE')) {
                $column = $tableMap->getColumn('TITLE');
            } elseif ($tableMap->hasColumn('LABEL')) {
                $column = $tableMap->getColumn('LABEL');
            }
            if (null !== $column) {
                $this->extraColumns[$column->getFullyQualifiedName()] = self::API_LIST_NAME_FIELD;
            } else {
                $this->addClassListName($tableMap);
            }
        }
    }

    /**
     * Add extra columns to pagination query
     *
     * @param ModelCriteria $query
     */
    private function addExtraColumns(ModelCriteria &$query)
    {
        if (self::API_ACTION_LIST === $this->action) {
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
     * Method that allow joins between models
     *
     * @param ModelCriteria $query
     */
    protected function joinTables(ModelCriteria &$query)
    {
        //TODO for specific implementations
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

    /**
     * Add filters fields to query
     *
     * @param ModelCriteria $query
     */
    private function addFilters(ModelCriteria &$query)
    {
        if (count($this->query) > 0) {
            $tableMap = $this->getTableMap();
            foreach ($this->query as $field => $value) {
                if (self::API_COMBO_FIELD === $field) {
                    ApiHelper::composerComboField($tableMap, $query, $this->extraColumns, $value);
                } else {
                    ApiHelper::addModelField($tableMap, $query, $field, $value);
                }
            }
        }
    }

    /**
     * @param ModelCriteria $query
     */
    private function checkReturnFields(ModelCriteria &$query)
    {
        $returnFields = $this->getRequest()->getQuery('__fields');
        if (null !== $returnFields) {
            $fields = explode(',', $returnFields);
            $select = [];
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
     * Generate list page for model
     */
    private function paginate()
    {
        $this->list = null;
        try {
            $query = $this->extractQuery();
            $this->joinTables($query);
            $this->addExtraColumns($query);
            $this->addFilters($query);
            $this->checkReturnFields($query);
            $this->addOrders($query);
            list($page, $limit) = $this->extractPagination();
            if ($limit == -1) {
                $this->list = $query->find($this->con);
            } else {
                $this->list = $query->paginate($page, $limit, $this->con);
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
        }
    }

    /**
     * Hydrate model from pk
     *
     * @param string $pk
     */
    private function hydrateModel($pk)
    {
        try {
            $query = $this->extractQuery();
            $this->joinTables($query);
            $this->addExtraColumns($query);
            $this->model = $query->findPk($pk);
        } catch (\Exception $e) {
            Logger::getInstance(get_class($this))->errorLog($e->getMessage());
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
     * Hydrate fields from request
     */
    private function hydrateFromRequest()
    {
        $class = new \ReflectionClass($this->getModelNamespace());
        $this->model = $class->newInstance();
        $this->model->fromArray($this->data);
    }

    /**
     * @label Get list of {__API__} elements filtered
     * @GET
     * @CACHE 600
     * @ROUTE /{__DOMAIN__}/api/{__API__}
     *
     * @return \PSFS\base\dto\JsonResponse(data=[{__API__}])
     */
    public function modelList()
    {
        $this->action = self::API_ACTION_LIST;
        $code = 200;
        list($return, $total, $pages) = $this->getList();

        return $this->json(new JsonResponse($return, ($code === 200), $total, $pages), $code);
    }

    /**
     * @label Get unique element for {__API__}
     *
     * @GET
     * @CACHE 600
     * @ROUTE /{__DOMAIN__}/api/{__API__}/{pk}
     *
     * @param int $pk
     *
     * @return \PSFS\base\dto\JsonResponse(data={__API__})
     */
    public function get($pk)
    {
        $this->action = self::API_ACTION_GET;
        $return = NULL;
        $total = NULL;
        $pages = 1;
        list($code, $return) = $this->getSingleResult($pk);

        return $this->json(new JsonResponse($return, ($code === 200), $total, $pages), $code);
    }

    /**
     * @label Create a new {__API__}
     *
     * @POST
     * @PAYLOAD {__API__}
     * @ROUTE /{__DOMAIN__}/api/{__API__}
     *
     * @return \PSFS\base\dto\JsonResponse(data={__API__})
     */
    public function post()
    {
        $this->action = self::API_ACTION_POST;
        $saved = FALSE;
        $status = 400;
        $model = NULL;
        try {
            $this->con->beginTransaction();
            $this->hydrateFromRequest();
            if (false !== $this->model->save($this->con)) {
                $status = 200;
                $saved = TRUE;
                $model = $this->model->toArray();
            }
        } catch (\Exception $e) {
            $model = _('Ha ocurrido un error intentando guardar el elemento: ') . $e->getMessage();
            Logger::log($e->getMessage(), LOG_ERR);
        }

        return $this->json(new JsonResponse($model, $saved), $status);
    }

    /**
     * @label Delete {__API__} model
     *
     * @DELETE
     * @ROUTE /{__DOMAIN__}/api/{__API__}/{pk}
     *
     * @param string $pk
     *
     * @return \PSFS\base\dto\JsonResponse(data={__API__})
     */
    public function delete($pk = NULL)
    {
        $this->action = self::API_ACTION_DELETE;
        $deleted = FALSE;
        $message = null;
        if (NULL !== $pk) {
            try {
                $this->con->beginTransaction();
                $this->hydrateModel($pk);
                if (NULL !== $this->model) {
                    $this->model->delete($this->con);
                    $deleted = TRUE;
                }
            } catch (\Exception $e) {
                $message = _('Ha ocurrido un error intentando eliminar el elemento, por favor verifica que no tenga otros elementos relacionados');
                Logger::getInstance(get_class($this->model))->errorLog($e->getMessage());
            }
        }

        return $this->json(new JsonResponse($message, $deleted), ($deleted) ? 200 : 400);
    }

    /**
     * @label Modify {__API__} model
     *
     * @PUT
     * @PAYLOAD {__API__}
     * @ROUTE /{__DOMAIN__}/api/{__API__}/{pk}
     *
     * @param string $pk
     *
     * @return \PSFS\base\dto\JsonResponse(data={__API__})
     *
     */
    public function put($pk)
    {
        $this->action = self::API_ACTION_PUT;
        $this->hydrateModel($pk);
        $status = 400;
        $updated = FALSE;
        $model = NULL;
        if (NULL !== $this->model) {
            try {
                $this->model->fromArray($this->data);
                if ($this->model->save($this->con) !== FALSE) {
                    $updated = TRUE;
                    $status = 200;
                    $model = $this->model->toArray();
                } else {
                    $model = _('Ha ocurrido un error intentando actualizar el elemento, por favor revisa los logs');
                }
            } catch (\Exception $e) {
                $model = $e->getMessage();
                Logger::getInstance(get_class($this->model))->errorLog($e->getMessage());
            }
        } else {
            $model = _('Ha ocurrido un error intentando actualizar el elemento, por favor revisa los logs');
        }

        return $this->json(new JsonResponse($model, $updated), $status);
    }

    /**
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    private function extractQuery()
    {
        $queryReflector = new \ReflectionClass($this->getModelNamespace() . "Query");
        /** @var \Propel\Runtime\ActiveQuery\ModelCriteria $query */
        $query = $queryReflector->getMethod('create')->invoke($this->con);

        return $query;
    }

    /**
     * Hydrate data from request
     */
    private function hydrateRequestData()
    {
        $request = Request::getInstance();
        $this->query = array_merge($this->query, $request->getQueryParams());
        $this->data = array_merge($this->data, $request->getData());
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
        Propel::closeConnections();

        return $this->_json($response, $status);
    }

    /**
     * Close transactions if are requireds
     *
     * @param int $status
     */
    private function closeTransaction($status)
    {
        $this->traceDebugQuery();
        if (null !== $this->con && $this->con->inTransaction()) {
            if ($status === 200) {
                $this->con->commit();
            } else {
                $this->con->rollBack();
            }
        }
    }

    /**
     * Trace debug query
     */
    private function traceDebugQuery()
    {
        if ($this->debug) {
            Logger::getInstance(get_class($this))->debugLog($this->con->getLastExecutedQuery());
        }
    }

    /**
     * Initialize db connection
     */
    private function createConnection()
    {
        $tableMap = $this->getModelTableMap();
        $this->con = Propel::getConnection($tableMap::DATABASE_NAME);
        $this->con->useDebug($this->debug);
    }

    private function getApi()
    {
        $model = explode("\\", $this->getModelNamespace());

        return $model[count($model) - 1];
    }

    public function getDomain()
    {
        $model = explode("\\", $this->getModelNamespace());
        return (strlen($model[0])) ? $model[0] : $model[1];
    }

    /**
     * @label {__API__} Manager
     * @GET
     * @route /admin/{__DOMAIN__}/{__API__}
     * @return string HTML
     */
    public function admin()
    {
        return AuthAdminController::getInstance()->render('api.admin.html.twig', array(
            "api" => $this->getApi(),
            "domain" => $this->getDomain(),
            "listLabel" => self::API_LIST_NAME_FIELD,
            'modelId' => self::API_MODEL_KEY_FIELD,
            'formUrl' => preg_replace('/\/\{(.*)\}$/i', '', $this->getRoute(strtolower('admin-api-form-' . $this->getDomain() . '-' . $this->getApi()), TRUE)),
            "url" => preg_replace('/\/\{(.*)\}$/i', '', $this->getRoute(strtolower($this->getDomain() . '-' . 'api-' . $this->getApi() . "-pk"), TRUE)),
        ), [], '');
    }

    protected function extractFields()
    {

    }

    /**
     * @label Returns form data for any entity
     * @POST
     * @visible false
     * @route /admin/api/form/{__DOMAIN__}/{__API__}
     * @return string JSON
     */
    public function getForm()
    {
        $map = $this->getModelTableMap();
        $form = ApiHelper::generateFormFields($map, $this->getDomain());
        $form->actions = ApiFormHelper::checkApiActions(get_class($this), $this->getDomain(), $this->getApi());

        return $this->json(new JsonResponse($form->toArray(), TRUE), 200);
    }

    /**
     * @return array
     */
    private function getList()
    {
        $return = array();
        $total = 0;
        $pages = 0;
        try {
            $this->paginate();
            if (null !== $this->list) {
                $return = $this->list->toArray(null, false, TableMap::TYPE_PHPNAME, false);
                $total = $this->list->getNbResults();
                $pages = $this->list->getLastPage();
            }
        } catch (\Exception $e) {
            Logger::getInstance(get_class($this))->errorLog($e->getMessage());
        }

        return array($return, $total, $pages);
    }

    /**
     * @param integer $pk
     *
     * @return array
     */
    private function getSingleResult($pk)
    {
        $model = $this->_get($pk);
        $code = 200;
        $return = array();
        if (NULL === $model || !method_exists($model, 'toArray')) {
            $code = 404;
        } else {
            $return = $model->toArray();
        }

        return array($code, $return);
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
        $this->extraColumns['CONCAT("' . $tableMap->getPhpName() . ' #", ' . $pks . ')'] = self::API_LIST_NAME_FIELD;
    }
}
