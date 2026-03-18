<?php

namespace PSFS\base\dto;

class Order extends Dto
{
    const ASC = 'ASC';
    const DESC = 'DESC';
    /**
     * @var array
 */
    protected $fields = array();

    /**
     *
     * @param string $field
     * @param string $direction
 */
    public function addOrder($field, $direction = self::ASC)
    {
        $this->fields[$field] = self::parseDirection($direction);
    }

    /**
     *
     * @param string $fieldToRemove
 */
    public function removeOrder($fieldToRemove)
    {
        $order = [];
        if (count($this->fields) > 0) {
            foreach ($this->getOrders() as $field => $direction) {
                if (strtolower($fieldToRemove) === strtolower($field)) {
                    continue;
                }
                $order[$field] = $direction;
            }
        }
        $this->fields = $order;
    }

    /**
     *
     * @param string $field
     * @param string $direction
 */
    public function setOrder($field, $direction = self::ASC)
    {
        $this->fields = [$field => self::parseDirection($direction)];
    }

    /**
     * @param string $direction
     *
     * @return string
 */
    public static function parseDirection($direction = self::ASC)
    {
        if (preg_match('/^asc$/i', $direction)) {
            return self::ASC;
        } else {
            return self::DESC;
        }
    }

    /**
     * @return array
 */
    public function getOrders()
    {
        return $this->fields;
    }

    /**
     * @param array $object
 */
    public function fromArray(array $object = [])
    {
        foreach ($object as $field => $order) {
            $this->addOrder($field, $order);
        }
    }

    /**
     * @return array
 */
    public function toArray()
    {
        return $this->getOrders();
    }
}
