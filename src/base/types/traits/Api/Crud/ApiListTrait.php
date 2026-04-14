<?php

namespace PSFS\base\types\traits\Api\Crud;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use PSFS\base\dto\Order;
use PSFS\base\Logger;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\ApiHelper;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\traits\Api\MutationTrait;

trait ApiListTrait
{
    use MutationTrait;

    /**
     * Contract: provided by ApiTrait.
     */
    abstract protected function prepareQuery();

    /**
     * Contract: provided by ApiTrait.
     *
     * @param ModelCriteria $query
     * @return mixed
     */
    abstract protected function checkReturnFields(ModelCriteria &$query);

    /**
     * @var array
     */
    protected $filters = array();

    /**
     * @Injectable
     * @var \PSFS\base\dto\Order
     */
    #[Injectable(class: Order::class)]
    protected Order $order;

    /**
     * @var \Propel\Runtime\Collection\Collection|\Propel\Runtime\Util\PropelModelPager
     */
    protected $list;


    protected function hydrateOrders()
    {
        if (count($this->query)) {
            Logger::log(static::class . ' gathering query string', LOG_DEBUG);
            foreach ($this->query as $key => $value) {
                if ($key === $this->apiOrderField()) {
                    $orders = json_decode($value, true);
                    foreach ($orders as $field => $direction) {
                        $this->order->addOrder($field, $direction);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    protected function extractPagination()
    {
        Logger::log(static::class . ' extract pagination start', LOG_DEBUG);
        $page = array_key_exists($this->apiPageField(), $this->query) ? $this->query[$this->apiPageField()] : 1;
        $limit = array_key_exists($this->apiLimitField(), $this->query) ? $this->query[$this->apiLimitField()] : 100;
        Logger::log(static::class . ' extract pagination end', LOG_DEBUG);
        return array($page, (int)$limit);
    }

    /**
     * @param ModelCriteria $query
     * @throws \PSFS\base\exception\ApiException
     */
    protected function addOrders(ModelCriteria &$query)
    {
        Logger::log(static::class . ' extract orders start ', LOG_DEBUG);
        $orderAdded = false;
        $tableMap = $this->getTableMap();
        foreach ($this->order->getOrders() as $field => $direction) {
            if ($column = ApiHelper::checkFieldExists($tableMap, $field)) {
                $orderAdded = true;
                if ($direction === Order::ASC) {
                    $query->addAscendingOrderByColumn($column->getPhpName());
                } else {
                    $query->addDescendingOrderByColumn($column->getPhpName());
                }
            }
        }
        if (!$orderAdded) {
            $pks = $this->getPkDbName();
            foreach (array_keys($pks) as $key) {
                $query->addAscendingOrderByColumn($key);
            }
        }
        Logger::log(static::class . ' extract orders end', LOG_DEBUG);
    }

    /**
     *
     * @param ModelCriteria $query
     */
    protected function addFilters(ModelCriteria &$query)
    {
        if (!empty($this->query)) {
            $tableMap = $this->getTableMap();
            foreach ($this->query as $field => $value) {
                if ($this->apiComboField() === $field) {
                    ApiHelper::composerComboField($tableMap, $query, $this->extraColumns, $value);
                } elseif (!preg_match('/^__/', $field)) {
                    ApiHelper::addModelField($tableMap, $query, $field, $value);
                }
            }
        }
    }


    protected function paginate()
    {
        $this->list = null;
        try {
            $query = $this->prepareQuery();
            $this->addFilters($query);
            $this->addOrders($query);
            list($page, $limit) = $this->extractPagination();
            $this->checkReturnFields($query);
            if ($limit === -1) {
                $this->list = $query->find($this->con);
            } else {
                $this->list = $query->paginate($page, $limit, $this->con);
            }
        } catch (\Exception $e) {
            Logger::log($e->getMessage(), LOG_ERR);
        }
    }

    private function apiComboField(): string
    {
        return Api::API_COMBO_FIELD;
    }

    private function apiOrderField(): string
    {
        return Api::API_ORDER_FIELD;
    }

    private function apiPageField(): string
    {
        return Api::API_PAGE_FIELD;
    }

    private function apiLimitField(): string
    {
        return Api::API_LIMIT_FIELD;
    }

}
