<?php
namespace PSFS\test\base;

use PSFS\test\examples\SingletonClassTest;

class SingletonTest extends \PHPUnit_Framework_TestCase {

    public function testCompleteSingletonCases()
    {
        $exampleClass = SingletonClassTest::getInstance();

        // Basic instance cases
        $this->assertNotNull($exampleClass, 'Error when instance the class');
        $this->assertInstanceOf('\\PSFS\\base\\Singleton', $exampleClass, 'Instance not valid');

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