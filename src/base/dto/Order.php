<?php
namespace PSFS\base\dto;

class Order extends Dto
{
    const ASC = 'ASC';
    const DESC = 'DESC';
    /**
     * Fields to use to order
     * @var array fields
     */
    protected $fields = array();

    /**
     * Add new order to dto
     *
     * @param string $field
     * @param string $direction
     */
    public function addOrder($field, $direction = self::ASC)
    {
        $this->fields[$field] = $direction;
    }

    /**
     * Remove existing order
     *
     * @param string $fieldToRemove
     */
    public function removeOrder($fieldToRemove)
    {
        $order = array();
        if (count($order) > 0) {
            foreach ($this->fields as $field => $direction) {
                if (strtolower($fieldToRemove) === strtolower($field)) {
                    continue;
                }
                $order[$field] = $direction;
            }
        }
        $this->fields = $order;
    }

    /**
     * Set an order field
     *
     * @param string $field
     * @param string $direction
     */
    public function setOrder($field, $direction = self::ASC)
    {
        $this->fields = [$field => $direction];
    }

    /**
     * Parse direction string
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
     * Return all order fields
     * @return array
     */
    public function getOrders()
    {
        return $this->fields;
    }

    /**
     * @param array $object
     */
    public function fromArray(array $object = array())
    {
        foreach($object as $field => $order) {
            $this->addOrder($field, $order);
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->fields;
    }
}