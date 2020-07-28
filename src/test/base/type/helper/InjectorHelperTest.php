<?php
namespace PSFS\test\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\test\examples\SingletonClassTest;
use ReflectionClass;
use ReflectionException;

class InjectorHelperTest extends TestCase
{

    /**
     * @return ReflectionClass
     * @throws ReflectionException
     */
    private function getExampleReflector()
    {
        return new ReflectionClass(SingletonClassTest::class);
    }

    /**
     * @throws ReflectionException
     */
    public function testInjector()
    {
        $reflector = $this->getExampleReflector();
        $variables = InjectorHelper::extractVariables($reflector);

        self::assertNotEmpty($variables, 'Reflection class can\'t extract variables');
        self::assertArrayHasKey('publicVariable', $variables, 'Public variable does not match');

        $properties = InjectorHelper::extractProperties($reflector);
        self::assertNotEmpty($properties, 'Reflection class can\'t extract properties');
        self::assertArrayHasKey('security', $properties, 'Property does not match');
        self::assertTrue(class_exists($properties['security']), 'Injectable class does not exists');

        foreach ($properties as $variable => $classNameSpace) {
            $injector = InjectorHelper::constructInjectableInstance($variable, true, $classNameSpace, $reflector->getName());
            self::assertNotNull($injector, 'Injector class is null');
            self::assertInstanceOf($classNameSpace, $injector, 'Injector has been created a different namespace than expected');
        }
    }
}
