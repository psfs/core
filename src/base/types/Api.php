<?php
    namespace PSFS\base\types;

    use Propel\Runtime\ActiveQuery\Criteria;
    use Propel\Runtime\ActiveQuery\ModelCriteria;
    use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
    use Propel\Runtime\Connection\ConnectionInterface;
    use Propel\Runtime\Map\ColumnMap;
    use Propel\Runtime\Map\TableMap;
    use Propel\Runtime\Propel;
    use PSFS\base\config\Config;
    use PSFS\base\dto\Field;
    use PSFS\base\dto\Form;
    use PSFS\base\dto\JsonResponse;
    use PSFS\base\dto\Order;
    use PSFS\base\Logger;
    use PSFS\base\Request;
    use PSFS\base\Router;
    use PSFS\base\types\helpers\ApiHelper;

    /**
     * Class Api
     * @package PSFS\base
     */
    abstract class Api extends Controller
    {
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
        protected $con;

        /**
         * @var bool debug
         */
        protected $debug = FALSE;

        /**
         * @var array extraColumns
         */
        protected $extraColumns = array();

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
         * Check if parametrized field exists in api model
         *
         * @param string $field
         *
         * @return bool
         */
        private function checkFieldExists($field)
        {
            return property_exists($this->getModelNamespace(), $field);
        }

        /**
         * Add order fields to query
         *
         * @param ModelCriteria $query
         */
        private function addOrders(ModelCriteria &$query)
        {
            $orderAdded = FALSE;
            foreach ($this->order->getOrders() as $field => $direction) {
                if ($this->checkFieldExists($field)) {
                    $orderAdded = TRUE;
                    if ($direction === Order::ASC) {
                        $query->addAscendingOrderByColumn($field);
                    } else {
                        $query->addDescendingOrderByColumn($field);
                    }
                }
            }
            if (!$orderAdded) {
                $query->addAscendingOrderByColumn(1);
            }
        }

        /**
         * Add extra columns to pagination query
         *
         * @param ModelCriteria $query
         */
        private function addExtraColumns(ModelCriteria &$query)
        {
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
         * Add filters fields to query
         *
         * @param ModelCriteria $query
         */
        private function addFilters(ModelCriteria &$query)
        {
            if (count($this->query) > 0) {
                foreach ($this->query as $field => $value) {
                    if ($this->checkFieldExists($field)) {
                        $tableField = ucfirst($field);
                        if (preg_match('/^<=/', $value)) {
                            $query->filterBy($tableField, substr($value, 2, strlen($value)), Criteria::LESS_EQUAL);
                        } elseif (preg_match('/^<=/', $value)) {
                            $query->filterBy($tableField, substr($value, 1, strlen($value)), Criteria::LESS_EQUAL);
                        } elseif (preg_match('/^>=/', $value)) {
                            $query->filterBy($tableField, substr($value, 2, strlen($value)), Criteria::GREATER_EQUAL);
                        } elseif (preg_match('/^>/', $value)) {
                            $query->filterBy($tableField, substr($value, 1, strlen($value)), Criteria::GREATER_THAN);
                        } elseif (preg_match('/^(\'|\")(.*)(\'|\")$/', $value)) {
                            $text = preg_replace('/(\'|\")/', '', $value);
                            $text = preg_replace('/\ /', '%', $text);
                            $query->filterBy($tableField, '%'.$text.'%', Criteria::LIKE);
                        } else {
                            $query->filterBy($tableField, $value, Criteria::EQUAL);
                        }
                    }
                }
            }
        }

        /**
         * Generate list page for model
         */
        private function paginate()
        {
            $this->list = array();
            try {
                $query = $this->extractQuery();
                $this->joinTables($query);
                $this->addFilters($query);
                $this->addOrders($query);
                $this->addExtraColumns($query);
                list($page, $limit) = $this->extractPagination();
                if ($limit == -1) {
                    $this->list = $query->find($this->con);
                } else {
                    $this->list = $query->paginate($page, $limit, $this->con);
                }
            } catch (\Exception $e) {
                Logger::getInstance(get_class($this))->errorLog($e->getMessage());
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
         * Get list of {__API__} elements filtered
         * @GET
         * @CACHE 600
         * @ROUTE /api/{__API__}
         *
         * @return \PSFS\base\dto\JsonResponse(data=[{__API__}])
         */
        public function modelList()
        {
            $code = 200;
            list($return, $total, $pages) = $this->getList();

            return $this->json(new JsonResponse($return, ($code === 200), $total, $pages), $code);
        }

        /**
         * Get unique element for {__API__}
         *
         * @GET
         * @CACHE 600
         * @ROUTE /api/{__API__}/{pk}
         *
         * @param int $pk
         *
         * @return \PSFS\base\dto\JsonResponse(data={__API__})
         */
        public function get($pk)
        {
            $return = NULL;
            $total = NULL;
            $pages = 1;
            list($code, $return) = $this->getSingleResult($pk);

            return $this->json(new JsonResponse($return, ($code === 200), $total, $pages), $code);
        }

        /**
         * Create new {__API__}
         *
         * @POST
         * @PAYLOAD {__API__}
         * @ROUTE /api/{__API__}
         *
         * @return \PSFS\base\dto\JsonResponse(data={__API__})
         */
        public function post()
        {
            $saved = FALSE;
            $status = 400;
            $model = NULL;
            try {
                $this->con->beginTransaction();
                $this->hydrateFromRequest();
                if ($this->model->save($this->con)) {
                    $status = 200;
                    $saved = TRUE;
                    $model = $this->model->toArray();
                }
            } catch (\Exception $e) {
                jpre($e->getMessage(), TRUE);
                Logger::getInstance()->errorLog($e->getMessage());
            }

            return $this->json(new JsonResponse($model, $saved), $status);
        }

        /**
         * Delete a {__API__}
         *
         * @DELETE
         * @ROUTE /api/{__API__}/{pk}
         *
         * @param string $pk
         *
         * @return \PSFS\base\dto\JsonResponse(data={__API__})
         */
        public function delete($pk = NULL)
        {
            $deleted = FALSE;
            if (NULL !== $pk) {
                try {
                    $this->con->beginTransaction();
                    $this->hydrateModel($pk);
                    if (NULL !== $this->model) {
                        $this->model->delete($this->con);
                        $deleted = TRUE;
                    }
                } catch (\Exception $e) {
                    Logger::getInstance(get_class($this->model))->errorLog($e->getMessage());
                }
            }

            return $this->json(new JsonResponse(NULL, $deleted), ($deleted) ? 200 : 400);
        }

        /**
         * Modify {__API__} model
         *
         * @PUT
         * @PAYLOAD {__API__}
         * @ROUTE /api/{__API__}/{pk}
         *
         * @param string $pk
         *
         * @return \PSFS\base\dto\JsonResponse(data={__API__})
         *
         */
        public function put($pk)
        {
            $this->hydrateModel($pk);
            $status = 400;
            $updated = FALSE;
            $model = NULL;
            if (NULL !== $this->model) {
                $this->con->beginTransaction();
                $this->model->fromArray($this->data);
                if ($this->model->save($this->con) !== FALSE) {
                    $updated = TRUE;
                    $status = 200;
                    $model = $this->model->toArray();
                }
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
            $query = $queryReflector->getMethod('create')->invoke(NULL);

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
         * Wrapper for json parent method with close transactions and close connectios tasks
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

            return parent::json($response, $status);
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

        /**
         * @GET
         * @visible false
         * @route /admin/{__API__}
         * @return string HTML
         */
        public function admin()
        {
            return $this->render('api.admin.html.twig', array(
                "api"    => $this->getApi(),
                "domain" => $this->domain,
                "url"    => preg_replace('/\/\{(.*)\}$/i', '', $this->getRoute(strtolower('api-' . $this->getApi() . "-pk"), TRUE)),
            ));
        }

        protected function extractFields()
        {

        }

        /**
         * Returns form data for any entity
         * @GET
         * @visible false
         * @route /api/form/{__API__}
         * @return string JSON
         */
        public function getForm()
        {
            $map = $this->getModelTableMap();
            $form = ApiHelper::generateFormFields($map);

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
                $return = $this->list->toArray();
                $total = $this->list->getNbResults();
                $pages = $this->list->getLastPage();
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
            if (NULL === $model && method_exists($model, 'toArray')) {
                $code = 404;
            } else {
                $return = $model->toArray();
            }

            return array($code, $return);
        }
    }
