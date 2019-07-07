<?php
namespace PSFS\test\examples;

use PSFS\base\dto\Dto;

/**
 * Class ComplexDto
 * @package PSFS\Test\examples
 */
class ComplexDto extends Dto {
    /**
     * @var \PSFS\base\dto\FormAction[]
     */
    public $actions = [];
    /**
     * @var \PSFS\base\dto\Order
     */
    public $order;
}