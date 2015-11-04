<?php
    namespace PSFS\base\types;

    use PSFS\base\dto\JsonResponse;
    use PSFS\base\dto\Order;
    use PSFS\base\Logger;
    use PSFS\base\Request;

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
         * @var \Propel\Runtime\Collection\Collection $list
         */
        protected $list;

        /**
         * @var array $filters
         */
        protected $filters;

        /**
         * @var Order $order
         */
        protected $order;

        /**
         * @var array $query
         */
        protected $query;

        /**
         * @var array $data
         */
        protected $data;

        /**
         *
         */
        public function __construct()
        {
            $request = Request::getInstance();
            $this->query = $request->getQueryParams();
            $this->data = $request->getData();
            $this->hydrateOrders();
        }

        abstract function getModelNamespace();

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
                    preg_match_all('/^\_\_order\[(.*)\]/', $key, $field);
                    pre($field, TRUE);
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
                $this->list = $query->paginate();
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
                $this->model = $query->findPk($pk);
            } catch (\Exception $e) {
                Logger::getInstance(get_class($this))->errorLog($e->getMessage());
            }
        }

        /**
         * Get unique element model or list of models filtered
         *
         * @GET
         * @CACHE 600
         * @ROUTE /{__API__}/{pk}
         *
         * @param null|string $pk
         *
         * @return JsonResponse JSON
         */
        public function get($pk = NULL)
        {
            $code = 200;
            if (NULL === $pk) {
                $this->paginate();
                $return = $this->list->toArray();

            } else {
                $this->hydrateModel($pk);
                $return = (NULL !== $this->getModel()) ? $this->getModel()->toArray() : NULL;
                if (NULL === $return) {
                    $code = 404;
                }
            }

            return $this->json(new JsonResponse($return, ($code === 200)), $code);
        }

        private function hydrateFromRequest()
        {
            $class = new \ReflectionClass($this->getModelNamespace());
            $this->model = $class->newInstance();
            $this->model->fromArray($this->data);
        }

        /**
         * Create new model
         *
         * @POST
         * @ROUTE /{__API__}
         *
         * @return JsonResponse JSON
         */
        public function post()
        {
            $saved = FALSE;
            $status = 400;
            $model = null;
            try {
                $this->hydrateFromRequest();
                if ($this->model->save()) {
                    $status = 200;
                    $saved = TRUE;
                    $model= $this->model->toArray();
                }
            } catch (\Exception $e) {
                Logger::getInstance()->errorLog($e->getMessage());
            }

            return $this->json(new JsonResponse($model, $saved), $status);
        }

        /**
         * Delete a model
         *
         * @DELETE
         * @ROUTE /{__API__}/{pk}
         *
         * @param string $pk
         *
         * @return JsonResponse JSON
         */
        public function delete($pk = NULL)
        {
            $deleted = FALSE;
            if (NULL !== $pk) {
                try {
                    $this->hydrateModel($pk);
                    if (NULL !== $this->model) {
                        $this->model->delete();
                        $deleted = TRUE;
                    }
                } catch (\Exception $e) {
                    Logger::getInstance(get_class($this->model))->errorLog($e->getMessage());
                }
            }

            return $this->json(new JsonResponse(null, $deleted), ($deleted) ? 200 : 400);
        }

        /**
         * Put model fields
         *
         * @PUT
         * @ROUTE /{__API__}/{pk}
         *
         * @param string $pk
         * @return JsonResponse
         *
         */
        public function put($pk)
        {
            $this->hydrateModel($pk);
            $status = 400;
            $updated = FALSE;
            $model = NULL;
            if (NULL !== $this->model) {
                $this->model->fromArray($this->data);
                if($this->model->save() !== false) {
                    $updated = true;
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

    }