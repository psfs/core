<?php
namespace PSFS\base\types\traits\Api\Crud;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use PSFS\base\dto\Order;
use PSFS\base\Logger;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\traits\Api\MutationTrait;

trait ApiListTrait {
    use MutationTrait;

    const API_COMBO_FIELD = '__combo';
    const API_ORDER_FIELD = '__order';
    const API_PAGE_FIELD = '__page';
    const API_LIMIT_FIELD = '__limit';

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
     * @var \Propel\Runtime\Collection\Collection|\Propel\Runtime\Util\PropelModelPager $list
     */
    protected $list;

    /**
     * Hydrate order from request
     */
    protected function hydrateOrders()
    {
        if (count($this->query)) {
            Logger::log(static::class . ' gathering query string', LOG_DEBUG);
            foreach ($this->query as $key => $value) {
                if ($key === self::API_ORDER_FIELD) {
                    $orders = json_decode($value, true);
                    foreach ($orders as $field => $direction) {
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
        Logger::log(static::class . ' extract pagination start', LOG_DEBUG);
        $page = array_key_exists(self::API_PAGE_FIELD, $this->query) ? $this->query[self::API_PAGE_FIELD] : 1;
        $limit = array_key_exists(self::API_LIMIT_FIELD, $this->query) ? $this->query[self::API_LIMIT_FIELD] : 100;
        Logger::log(static::class . ' extract pagination end', LOG_DEBUG);
        return array($page, (int)$limit);
    }

    /**
     * Add order fields to query
     * @param ModelCriteria $query
     * @throws \PSFS\base\exception\ApiException
     */
    protected function addOrders(ModelCriteria &$query)
    {
        Logger::log(static::class . ' extract orders start ', LOG_DEBUG);
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
            $pks = $this->getPkDbName();
            foreach(array_keys($pks) as $key) {
                $query->addAscendingOrderByColumn($key);
            }
        }
        Logger::log(static::class . ' extract orders end', LOG_DEBUG);
    }

    /**
     * Add filters fields to query
     *
     * @param ModelCriteria $query
     */
    protected function addFilters(ModelCriteria &$query)
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
    protected function paginate()
    {
        $this->list = null;
        try {
            $query = $this->prepareQuery();
            $this->addFilters($query);
            $this->addOrders($query);
            list($page, $limit) = $this->extractPagination();
            if ($limit === -1) {
                $this->list = $query->find($this->con);
            } else {
                $this->list = $query->paginate($page, $limit, $this->con);
            }
            $this->checkReturnFields($this->list->getQuery());
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
        }
    }

}