<?php
namespace PSFS\Test\base\dto;

use PHPUnit\Framework\TestCase;
use PSFS\base\dto\Field;
use PSFS\base\dto\Form;
use PSFS\base\dto\FormAction;
use PSFS\base\dto\JsonResponse;
use PSFS\base\dto\Order;
use PSFS\base\dto\ProfilingJsonResponse;
use PSFS\test\examples\ComplexDto;

/**
 * Class DtoTest
 * @package PSFS\Test\base\dto
 */
class DtoTest extends TestCase {

    private function getExampleData() {
        return [
            'fields' => [
                ['name' => '3.hidden', 'label' => 'Hidden field', 'type' => Field::HIDDEN_TYPE, 'required' => false],
                ['name' => '4.text', 'label' => 'Text field', 'type' => Field::TEXT_TYPE, 'required' => false],
                ['name' => '2.number', 'label' => 'Number field', 'type' => Field::NUMBER_TYPE, 'required' => true],
                ['name' => '5.textarea', 'label' => 'Textarea field', 'type' => Field::TEXTAREA_TYPE, 'required' => false],
                ['name' => '6.date', 'label' => 'Date field', 'type' => Field::DATE, 'required' => false],
                ['name' => '1.test6', 'label' => 'Test 1', 'type' => Field::TEXT_TYPE, 'required' => true],
            ],
            'actions' => [
                ['label' => 'test1', 'url' => 'test1', 'method' => 'test1' ],
                ['label' => 'test2', 'url' => 'test2', 'method' => 'test2' ],
                ['label' => 'test3', 'url' => 'test3', 'method' => 'test3' ],
            ],
            'order' => [
                'test' => Order::ASC,
                'test2' => Order::DESC,
            ],
            'boolean' => true,
            'number' => 5,
            'decimal' => 3.1415,
        ];
    }

    /**
     * Test for Form and Field Dto
     * @throws \PSFS\base\exception\GeneratorException
     * @throws \ReflectionException
     */
    public function testFormDto() {
        $form1 = new Form(true);
        $form2 = new Form();
        $this->assertEquals($form1->toArray(), $form2->toArray(), 'Error on creation for basic dto');
        $this->assertEquals($form1->actions, $form2->actions, 'Different actions in dto from scratch');
        $this->assertEquals($form1->fieldExists('test'), $form2->fieldExists('test'), 'Different check for fields that not exists');

        $field = new Field('test', 'test');
        $form1->addField($field);
        $this->assertTrue($form1->fieldExists($field->name), 'Error adding new field to a form');
        $this->assertNotEquals($form1->toArray(), $form2->toArray(), 'Bad extraction for form data');

        $reflection = new \ReflectionClass(Field::class);
        foreach($reflection->getConstants() as $constant) {
            $this->assertNotNull(Form::fieldsOrder(['type' => $constant]));
        }
    }

    public function testPopulatingDto() {
        // Populating data for more testing
        $form1 = new Form(true);
        $form2 = new Form();
        $exampleData = $this->getExampleData();
        $emptyAction = new FormAction(false);
        foreach($exampleData['actions'] as $actionData) {
            $action = clone $emptyAction;
            $action->fromArray($actionData);
            $form1->actions[] = $action;
            $form2->actions[] = $action;
        }
        foreach($exampleData['fields'] as $fieldData) {
            $field = new Field($fieldData['name'], $fieldData['label'], $fieldData['type']);
            $field->required = $fieldData['required'];
            $form1->addField($field);
            $form2->addField($field);
            $this->assertEquals($form1->fieldExists($field->name), $form2->fieldExists($field->name), 'Error adding new field');
        }
        $formExportData = $form1->toArray();
        $this->assertEquals($formExportData, $form2->toArray(), 'Error on export for complex dto');
        $this->assertEquals($form1->actions, $form2->actions, 'Different actions in dto with populated data');
        /**
         * Checking order
         * First required, then see into Form dto method
         * In this case, the order is before the field name --> 1.number, 6.test6, 3.hidden, 4.text, 5.textarea, 6.date
         */
        $order = ['1.test6', '2.number', '3.hidden', '4.text', '5.textarea', '6.date'];
        foreach($order as $index => $field) {
            $this->assertEquals($field, $formExportData['fields'][$index]['name'], 'Order is not right');
        }
    }

    /**
     * Test Dto basics
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function testDtoBasics() {
        // Initialize classes to test
        $action = new FormAction(false);
        $action2 = clone $action;
        $emptyAction = clone $action;
        $order = new Order(false);
        $order2 = clone $order;
        $emptyOrder = clone $order;
        $complextDto = new ComplexDto(false);
        $complextDto2 = clone $complextDto;

        $this->assertEquals($action, $action2, 'Error cloning dtos');
        $this->assertEquals($order, $order2, 'Error cloning dtos');
        $this->assertEquals($complextDto, $complextDto2, 'Error cloning dtos');

        // Populate tests
        $exampleData = $this->getExampleData();

        // Manual creation vs hydration
        $action->fromArray($exampleData['actions'][0]);
        $action2->label = 'test1';
        $action2->url = 'test1';
        $action2->method = 'test1';
        $this->assertEquals($action, $action2, 'Different values from hydration');
        $this->assertEquals($action->toArray(), $action2->toArray(), 'Different values on export');

        $order->fromArray($exampleData['order']);
        foreach($exampleData['order'] as $field => $ord) {
            $order2->addOrder($field, $ord);
        }
        $this->assertEquals($order, $order2, 'Different values from hydration');
        $this->assertEquals($order->toArray(), $order2->toArray(), 'Different values on export');
        $order2->removeOrder('test');
        $this->assertNotEquals($order, $order2, 'Remove field order failed in object');
        $this->assertNotEquals($order->toArray(), $order2->toArray(), 'Remove field order failed as array');

        // Multiple creation for dtos from array to create complex dto
        $action1 = clone $emptyAction;
        $action1->fromArray($exampleData['actions'][0]);
        $complextDto->actions[] = $action1;
        $action2 = clone $emptyAction;
        $action2->fromArray($exampleData['actions'][1]);
        $complextDto->actions[] = $action2;
        $action3 = clone $emptyAction;
        $action3->fromArray($exampleData['actions'][2]);
        $complextDto->actions[] = $action3;
        $emptyOrder->setOrder('test', Order::ASC);
        $this->assertEquals(1, count($emptyOrder->getOrders()), 'Distinct number or orders created');
        $emptyOrder->addOrder('test2', Order::DESC);
        $this->assertEquals(2, count($emptyOrder->getOrders()), 'Distinct number or orders added');
        $emptyOrder->addOrder('test3', Order::DESC);
        $this->assertEquals(3, count($emptyOrder->getOrders()), 'Distinct number or orders added');
        $emptyOrder->removeOrder('test3');
        $this->assertEquals(2, count($emptyOrder->getOrders()), 'Distinct number or orders removed');
        $complextDto->order = $emptyOrder;
        // Creation from import
        $complextDto2->fromArray($exampleData);
        $this->assertEquals($complextDto->jsonSerialize(), $complextDto2->jsonSerialize(), 'Different values on export');
        $this->assertEquals($complextDto->order->jsonSerialize(), $complextDto2->order->jsonSerialize(), 'Different order values on export');
        $this->assertEquals($complextDto->__toString(), $complextDto2->__toString(), 'Different values fot toString');
    }

    /**
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function testJsonResponseDto() {
        $jsonResponse = new JsonResponse();
        $profilling = ProfilingJsonResponse::createFromPrevious($jsonResponse, ['test' => true]);
        $this->assertNotEquals($jsonResponse->toArray(), $profilling->toArray(), 'Profilling error creation');
        $this->assertEquals($jsonResponse->data, $profilling->data, 'Error creating profilling dto');
        $this->assertEquals($jsonResponse->success, $profilling->success, 'Error creating profilling dto');
        $this->assertEquals($jsonResponse->message, $profilling->message, 'Error creating profilling dto');
        $this->assertEquals($jsonResponse->total, $profilling->total, 'Error creating profilling dto');
        $this->assertEquals($jsonResponse->pages, $profilling->pages, 'Error creating profilling dto');
        $this->assertNotEmpty($profilling->profiling, 'Error creating profilling dto');
    }
}
