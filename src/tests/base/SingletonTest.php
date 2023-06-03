<?php
namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Singleton;
use PSFS\tests\examples\SingletonClassTest;

/**
 * Class SingletonTest
 * @package PSFS\tests\base
 */
class SingletonTest extends TestCase {

    /**
     * @covers
     * @return void
     * @throws \ReflectionException
     */
    public function testCompleteSingletonCases()
    {
        $exampleClass = SingletonClassTest::getInstance();

        // Basic instance cases
        $this->assertNotNull($exampleClass, 'Error when instance the class');
        $this->assertInstanceOf(Singleton::class, $exampleClass, 'Instance not valid');

        // Singleton pattern cases
        $example2 = SingletonClassTest::getInstance();
        $this->assertEquals($exampleClass, $example2, 'Singleton pattern not found');

        // Extended functionality cases
        $this->assertEquals('SingletonClassTest', $exampleClass->getShortName(), 'The short name is not equals than expected');
        $exampleClass->init();

        $var = date('Y');
        $exampleClass->fieldTest = $var;
        $this->assertNotNull($exampleClass->fieldTest, 'Assignation for private var not found');
        $this->assertEquals($exampleClass->fieldTest, $var, 'Field has not the same value');

    }
}