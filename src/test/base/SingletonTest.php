<?php
namespace PSFS\test\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\Singleton;
use PSFS\test\examples\SingletonClassTest;

/**
 * Class SingletonTest
 * @package PSFS\test\base
 */
class SingletonTest extends TestCase {

    public function testCompleteSingletonCases()
    {
        $exampleClass = SingletonClassTest::getInstance();

        // Basic instance cases
        self::assertNotNull($exampleClass, 'Error when instance the class');
        self::assertInstanceOf(Singleton::class, $exampleClass, 'Instance not valid');

        // Singleton pattern cases
        $example2 = SingletonClassTest::getInstance();
        self::assertEquals($exampleClass, $example2, 'Singleton pattern not found');

        // Extended functionality cases
        self::assertEquals('SingletonClassTest', $exampleClass->getShortName(), 'The short name is not equals than expected');
        $exampleClass->init();

        $var = date('Y');
        $exampleClass->fieldTest = $var;
        self::assertNotNull($exampleClass->fieldTest, 'Assignation for private var not found');
        self::assertEquals($exampleClass->fieldTest, $var, 'Field has not the same value');

    }
}