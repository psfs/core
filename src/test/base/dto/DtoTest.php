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
        foreach($reflection->getConstants() as $contant) {
            $this->assertNotNull(Form::fieldsOrder(['type' => $contant]));
        }
    }

    /**
     * Test Dto basics
     * @throws \PSFS\base\exception\GeneratorException
     */
    public function testDtoBasics() {
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

        $exampleData = [
            'actions' => [
                ['label' => 'test1', 'url' => 'test1', 'method' => 'test1' ],
                ['label' => 'test2', 'url' => 'test2', 'method' => 'test2' ],
                ['label' => 'test3', 'url' => 'test3', 'method' => 'test3' ],
            ],
            'order' => [
                'test' => Order::ASC,
                'test2' => Order::DESC,
            ]
        ];

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

        // Multiple creation for dtos from array
        $action1 = clone $emptyAction;
        $action1->label = 'test1';
        $action1->url = 'test1';
        $action1->method = 'test1';
        $complextDto->actions[] = $action1;
        $action2 = clone $emptyAction;
        $action2->label = 'test2';
        $action2->url = 'test2';
        $action2->method = 'test2';
        $complextDto->actions[] = $action2;
        $action3 = clone $emptyAction;
        $action3->label = 'test3';
        $action3->url = 'test3';
        $action3->method = 'test3';
        $complextDto->actions[] = $action3;
        $emptyOrder->addOrder('test', Order::ASC);
        $emptyOrder->addOrder('test2', Order::DESC);
        $complextDto->order = $emptyOrder;
        $complextDto2->fromArray($exampleData);
        $this->assertEquals($complextDto->toArray(), $complextDto2->toArray(), 'Different values on export');
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