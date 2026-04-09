<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\tests\examples\AttributeInjectableSingletonTestExample;
use PSFS\tests\examples\SingletonClassTestExample;
use PSFS\base\Security;
use ReflectionClass;
use ReflectionException;

class InjectorHelperTest extends TestCase
{

    /**
     * @return ReflectionClass
     */
    private function getExampleReflector()
    {
        return new ReflectionClass(SingletonClassTestExample::class);
    }

    /**
     * @throws ReflectionException
     */
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
            $injector = InjectorHelper::constructInjectableInstance($variable, true, $classNameSpace, $reflector->getName());
            $this->assertNotNull($injector, 'Injector class is null');
            $this->assertInstanceOf($classNameSpace, $injector, 'Injector has been created a different namespace than expected');
        }
    }

    public function testAttributeInjectableSupportWhenMetadataAttributesEnabled(): void
    {
        $configBackup = Config::getInstance()->dumpConfig();
        try {
            $override = $configBackup;
            $override['metadata.attributes.enabled'] = true;
            Config::save($override, []);
            Config::getInstance()->loadConfigData(true);

            $reflector = new ReflectionClass(AttributeInjectableSingletonTestExample::class);
            $properties = InjectorHelper::extractProperties($reflector);
            $this->assertArrayHasKey('security', $properties);
            $this->assertEquals('\\PSFS\\base\\Security', $properties['security']);
        } finally {
            Config::save($configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
    }

    public function testExtractPropertiesThrowsWhenInjectablePropertyIsNotProtected(): void
    {
        $configBackup = Config::getInstance()->dumpConfig();
        try {
            $override = $configBackup;
            $override['metadata.attributes.enabled'] = true;
            Config::save($override, []);
            Config::getInstance()->loadConfigData(true);

            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('must be protected');

            $reflector = new ReflectionClass(InvalidInjectableVisibilityExample::class);
            InjectorHelper::extractProperties($reflector);
        } finally {
            Config::save($configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
    }

    public function testResolveInjectableRuntimeDefinitionUsesAttributeRuntimeFlags(): void
    {
        $configBackup = Config::getInstance()->dumpConfig();
        try {
            $override = $configBackup;
            $override['metadata.attributes.enabled'] = true;
            Config::save($override, []);
            Config::getInstance()->loadConfigData(true);

            $definition = InjectorHelper::resolveInjectableRuntimeDefinition(
                RuntimeInjectableDefinitionExample::class,
                'security',
                '\\PSFS\\base\\Security'
            );

            $this->assertTrue($definition['isInjectable']);
            $this->assertSame('\\PSFS\\base\\Security', $definition['class']);
            $this->assertFalse($definition['singleton']);
            $this->assertFalse($definition['required']);
            $this->assertSame('attribute', $definition['source']);
        } finally {
            Config::save($configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
    }
}

class InvalidInjectableVisibilityExample
{
    #[Injectable(class: Security::class)]
    private $security;
}

class RuntimeInjectableDefinitionExample
{
    #[Injectable(class: Security::class, singleton: false, required: false)]
    protected $security;
}
