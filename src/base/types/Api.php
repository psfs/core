<?php
namespace PSFS\base\types;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\TableMap;
use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\dto\Order;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\traits\Api\ManagerTrait;

/**
 * Class Api
 * @package PSFS\base
 */
abstract class Api extends Singleton
{
    use ManagerTrait;

    const API_COMBO_FIELD = '__combo';
    const API_LIST_NAME_FIELD = '__name__';
    const API_FIELDS_RESULT_FIELD = '__fields';
    const API_MODEL_KEY_FIELD = '__pk';
    const API_ORDER_FIELD = '__order';
    const API_PAGE_FIELD = '__page';
    const API_LIMIT_FIELD = '__limit';
    const API_PK_SEPARATOR = '__|__';

    const API_ACTION_LIST = 'list';
    const API_ACTION_GET = 'read';
    const API_ACTION_POST = 'create';
    const API_ACTION_PUT = 'update';
    const API_ACTION_DELETE = 'delete';

    const HEADER_API_TOKEN = 'X-API-SEC-TOKEN';

    /**
     * @var \Propel\Runtime\Collection\Collection|\Propel\Runtime\Util\PropelModelPager $list
     */
    protected $list;

    /**
     * @var array $filters
     */
    protected $filters = array();

    /**
     * @Injectable
     * @var \PSFS\base\dto\Order $order
     */
    protected $order;

    /**
     * @var array $query
     */
    protected $query = array();

    /**
     * @var string
     */
    protected $domain;

    /**
     * Initialize api
     */
    public function init()
    {
        parent::init();
        Logger::log(get_called_class() . ' init', LOG_DEBUG);
        $this->domain = $this->getDomain();
        $this->hydrateRequestData();
        $this->hydrateOrders();
        $this->createConnection($this->getTableMap());
        $this->setLoaded(true);
        Logger::log(get_called_class() . ' loaded', LOG_DEBUG);
    }

    /**
     * Hydrate order from request
     */
    private function hydrateOrders()
    {
        if (count($this->query)) {
            Logger::log(get_called_class() . ' gathering query string', LOG_DEBUG);
            foreach ($this->query as $key => $value) {
                if ($key === self::API_ORDER_FIELD) {
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
    protected function extractPagination()
    {
        Logger::log(get_called_class() . ' extract pagination start', LOG_DEBUG);
        $page = (array_key_exists(self::API_PAGE_FIELD, $this->query)) ? $this->query[self::API_PAGE_FIELD] : 1;
        $limit = (array_key_exists(self::API_LIMIT_FIELD, $this->query)) ? $this->query[self::API_LIMIT_FIELD] : 100;
        Logger::log(get_called_class() . ' extract pagination end', LOG_DEBUG);
        return array($page, $limit);
    }

    /**
     * Add order fields to query
     *
     * @param ModelCriteria $query
     */
    private function addOrders(ModelCriteria &$query)
    {
        Logger::log(get_called_class() . ' extract orders start ', LOG_DEBUG);
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
            foreach($this->getPkDbName() as $pk => $phpName) {
                $query->addAscendingOrderByColumn($pk);
            }
        }
        Logger::log(get_called_class() . ' extract orders end', LOG_DEBUG);
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
                } elseif(!preg_match('/^__/', $field)) {
                    ApiHelper::addModelField($tableMap, $query, $field, $value);
                }
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
            $query = $this->prepareQuery();
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
        $message = null;
        if(!$total) {
            $message = _('No se han encontrado elementos para la búsqueda');
        }

        return $this->json(new JsonResponse($return, ($code === 200), $total, $pages, $message), $code);
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
        $message = null;
        list($code, $return) = $this->getSingleResult($pk);
        if($code !== 200) {
            $message = _('No se ha encontrado el elemento solicitado');
        }

        return $this->json(new JsonResponse($return, ($code === 200), $total, $pages, $message), $code);
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
        $message = null;
        try {
            $this->hydrateFromRequest();
            if (false !== $this->model->save($this->con)) {
                $status = 200;
                $saved = TRUE;
                $model = $this->model->toArray();
            } else {
                $message = _('No se ha podido modificar el modelo seleccionado');
            }
        } catch (\Exception $e) {
            $message = _('Ha ocurrido un error intentando guardar el elemento: ') .'<br>'. $e->getMessage();
            Logger::log($e->getMessage(), LOG_ERR);
        }

        return $this->json(new JsonResponse($model, $saved, $saved, 0, $message), $status);
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
        $message = null;
        if (NULL !== $this->model) {
            try {
                $this->hydrateModelFromRequest($this->model, $this->data);
                if ($this->model->save($this->con) !== FALSE) {
                    $updated = TRUE;
                    $status = 200;
                    $model = $this->model->toArray();
                } else {
                    $message = _('Ha ocurrido un error intentando actualizar el elemento, por favor revisa los logs');
                }
            } catch (\Exception $e) {
                $message = $e->getMessage();
                Logger::getInstance(get_class($this->model))->errorLog($e->getMessage());
            }
        } else {
            $message = _('No se ha encontrado el modelo al que se hace referencia para actualizar');
        }

        return $this->json(new JsonResponse($model, $updated, $updated, 0, $message), $status);
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

        return $this->json(new JsonResponse(null, $deleted, $deleted, 0, $message), ($deleted) ? 200 : 400);
    }

    /**
     * Hydrate data from request
     */
    private function hydrateRequestData()
    {
        $request = Request::getInstance();
        $this->query = array_merge($this->query, $request->getQueryParams());
        $this->data = array_merge($this->data, $request->getRawData());
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
            Logger::log(get_class($this) . ': ' . $e->getMessage(), LOG_ERR);
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
}
