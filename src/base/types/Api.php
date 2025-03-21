<?php

namespace PSFS\base\types;

use Propel\Runtime\Collection\ArrayCollection;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Util\PropelModelPager;
use PSFS\base\config\Config;
use PSFS\base\dto\JsonResponse;
use PSFS\base\Logger;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\traits\Api\Crud\ApiListTrait;
use PSFS\base\types\traits\Api\ManagerTrait;

/**
 * Class Api
 * @package PSFS\base
 */
abstract class Api extends Singleton
{
    use ManagerTrait, ApiListTrait;

    const API_LIST_NAME_FIELD = '__name__';
    const API_FIELDS_RESULT_FIELD = '__fields';
    const API_MODEL_KEY_FIELD = '__pk';
    const API_PK_SEPARATOR = '__|__';

    const API_ACTION_LIST = 'list';
    const API_ACTION_GET = 'read';
    const API_ACTION_POST = 'create';
    const API_ACTION_PUT = 'update';
    const API_ACTION_DELETE = 'delete';
    const API_ACTION_BULK = 'bulk';

    const HEADER_API_TOKEN = 'X-API-SEC-TOKEN';
    const HEADER_API_LANG = 'X-API-LANG';
    const HEADER_API_FIELDTYPE = 'X-FIELD-TYPE';

    /**
     * @var string
     */
    protected $domain;

    public function __construct(...$args)
    {
        $this->checkActions($args[0] ?? null);
        parent::__construct();
    }

    /**
     * Initialize api
     */
    public function init()
    {
        parent::init();
        Logger::log(static::class . ' init', LOG_DEBUG);
        $this->domain = $this->getDomain();
        $this->hydrateRequestData();
        $this->hydrateOrders();
        if ($this instanceof CustomApi === false) {
            $this->createConnection($this->getTableMap());
        }
        $this->checkFieldType();
        $this->setLoaded(true);
        Logger::log(static::class . ' loaded', LOG_DEBUG);
    }

    private function checkActions($method)
    {
        switch ($method) {
            default:
            case 'modelList':
                $this->action = self::API_ACTION_LIST;
                break;
            case 'get':
                $this->action = self::API_ACTION_GET;
                break;
            case 'post':
                $this->action = self::API_ACTION_POST;
                break;
            case 'put':
                $this->action = self::API_ACTION_PUT;
                break;
            case 'delete':
                $this->action = self::API_ACTION_DELETE;
                break;
            case 'bulk':
                $this->action = self::API_ACTION_BULK;
                break;
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
        if (!$total) {
            $message = t('No se han encontrado elementos para la búsqueda');
        }

        return $this->json(new JsonResponse($return, $code === 200, $total, $pages, $message), $code);
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
        $total = NULL;
        $pages = 1;
        $message = null;
        list($code, $return) = $this->getSingleResult($pk);
        if ($code !== 200) {
            $message = t('No se ha encontrado el elemento solicitado');
        }

        return $this->json(new JsonResponse($return, $code === 200, $total, $pages, $message), $code);
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
                $model = $this->model->toArray($this->fieldType ?: TableMap::TYPE_PHPNAME, true, [], true);
            } else {
                $message = t('No se ha podido guardar el modelo seleccionado');
            }
        } catch (\Exception $e) {
            if(Config::getParam('debug')) {
                $message = t('Ha ocurrido un error intentando guardar el elemento: ') . '<br>' . $e->getMessage();
            } else {
                $message = t('Ha ocurrido un error intentando guardar el elemento: ') . '<br>' . $e->getCode();
            }
            $context = [];
            if (null !== $e->getPrevious()) {
                $context[] = $e->getPrevious()->getMessage();
            }
            Logger::log($e->getMessage(), LOG_CRIT, $context);
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
                    $model = $this->model->toArray($this->fieldType ?: TableMap::TYPE_PHPNAME, true, [], true);
                } else {
                    $message = t('Ha ocurrido un error intentando actualizar el elemento, por favor revisa los logs');
                }
            } catch (\Exception $e) {
                if(Config::getParam('debug')) {
                    $message = t('Ha ocurrido un error intentando actualizar el elemento: ') . '<br>' . $e->getMessage();
                } else {
                    $message = t('Ha ocurrido un error intentando actualizar el elemento, por favor revisa los logs: ') . '<br>' . $e->getCode();
                }
                $context = [];
                if (null !== $e->getPrevious()) {
                    $context[] = $e->getPrevious()->getMessage();
                }
                Logger::log($e->getMessage(), LOG_CRIT, $context);
            }
        } else {
            $message = t('No se ha encontrado el modelo al que se hace referencia para actualizar');
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
        $this->closeTransaction(200);
        $deleted = FALSE;
        $message = null;
        if (NULL !== $pk) {
            try {
                $this->con->beginTransaction();
                $this->hydrateModel($pk);
                if (NULL !== $this->model) {
                    if (method_exists('clearAllReferences', $this->model)) {
                        $this->model->clearAllReferences(true);
                    }
                    $this->model->delete($this->con);
                    $deleted = TRUE;
                }
            } catch (\Exception $e) {
                $context = [];
                if (null !== $e->getPrevious()) {
                    $context[] = $e->getPrevious()->getMessage();
                }
                Logger::log($e->getMessage(), LOG_CRIT, $context);

            }
        }

        return $this->json(new JsonResponse(null, $deleted, $deleted, 0, $message), ($deleted) ? 200 : 400);
    }

    /**
     * @label Bulk insert for {__API__} model
     * @POST
     * @route /{__DOMAIN__}/api/{__API__}s
     *
     * @payload [{__API__}]
     * @return \PSFS\base\dto\JsonResponse(data=[{__API__}])
     */
    public function bulk()
    {
        $this->action = self::API_ACTION_BULK;
        $saved = FALSE;
        $status = 400;
        $message = null;
        try {
            $this->hydrateBulkRequest();
            $this->saveBulk();
            $saved = true;
            $status = 200;
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_CRIT, $this->getRequest()->getData());
            $message = t('Bulk insert rolled back');
        }
        return $this->json(new JsonResponse($this->exportList(), $saved, count($this->list), 1, $message), $status);
    }

    private function extractDataWithFormat()
    {
        $return = [];

        /** @var CustomerTableMap $tableMap */
        $modelPk = ApiHelper::extractPrimaryKeyColumnName($this->getTableMap());
        foreach ($this->list->getData() as $data) {
            $return[] = ApiHelper::mapArrayObject($this->getModelNamespace(), $modelPk, $this->query, $data);
        }
        return $return;
    }

    /**
     * @return array
     */
    private function getList()
    {
        $return = [];
        $total = 0;
        $pages = 0;
        try {
            $this->paginate();
            if (null !== $this->list) {
                if (array_key_exists(self::API_FIELDS_RESULT_FIELD, $this->query) && Config::getParam('api.field.types')) {
                    $return = $this->extractDataWithFormat();
                } else {
                    $return = $this->list->toArray(null, false, $this->fieldType ?: TableMap::TYPE_PHPNAME, false);
                }
                $total = 0;
                $pages = 0;
                if ($this->list instanceof PropelModelPager) {
                    $total = $this->list->getNbResults();
                    $pages = $this->list->getLastPage();
                } elseif ($this->list instanceof ArrayCollection) {
                    $total = count($return);
                    $pages = 1;
                }
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
            $return = $model->toArray($this->fieldType ?: TableMap::TYPE_PHPNAME, true, [], true);
        }

        return array($code, $return);
    }
}
