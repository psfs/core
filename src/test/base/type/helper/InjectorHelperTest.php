<?php
namespace PSFS\test\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\test\examples\SingletonClassTest;

class InjectorHelperTest extends TestCase
{

    /**
     * @return \ReflectionClass
     */
    private function getExampleReflector()
    {
        return new \ReflectionClass(SingletonClassTest::class);
    }

    public function testInjector()
    {
        $reflector = $this->getExampleReflector();
        $variables = InjectorHelper::extractVariables($reflector);

        $this->assertNotEmpty($variables, 'Reflection class can\'t extract variables');
        $this->assertArrayHasKey('publicVariable', $variables, 'Public variable does not match');

        $properties = InjectorHelper::extractProperties($reflector);
        $this->assertNotEmpty($properties, 'Reflection class can\'t extract properties');
        $this->assertArrayHasKey('security', $properties, 'Property does not match');
        $this->assertTrue(class_exists($properties['security']), 'Injectable class does not exists');

        foreach ($properties as $variable => $classNameSpace) {
            $injector = InjectorHelper::constructInyectableInstance($variable, true, $classNameSpace, $reflector->getName());
            $this->assertNotNull($injector, 'Injector class is null');
            $this->assertInstanceOf($classNameSpace, $injector, 'Injector has been created a different namespace than expected');
        }
    }
}
