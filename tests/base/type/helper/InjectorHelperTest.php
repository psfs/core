<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\Values;
use PSFS\base\types\helpers\InjectorHelper;
use PSFS\tests\examples\AttributeInjectableSingletonTestExample;
use PSFS\tests\examples\SingletonClassTestExample;
use PSFS\base\Security;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

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

    public function testResolveInjectableRuntimeDefinitionFallsBackToCachedDefinitionsWhenPropertyIsMissing(): void
    {
        $fromString = InjectorHelper::resolveInjectableRuntimeDefinition(
            RuntimeInjectableDefinitionExample::class,
            'missingProperty',
            '\\PSFS\\base\\Security'
        );

        $this->assertTrue($fromString['isInjectable']);
        $this->assertSame('\\PSFS\\base\\Security', $fromString['class']);
        $this->assertTrue($fromString['singleton']);
        $this->assertTrue($fromString['required']);
        $this->assertSame('cache', $fromString['source']);

        $fromArray = InjectorHelper::resolveInjectableRuntimeDefinition(
            RuntimeInjectableDefinitionExample::class,
            'missingProperty',
            [
                'class' => '\\PSFS\\base\\Router',
                'singleton' => false,
                'required' => false,
            ]
        );

        $this->assertTrue($fromArray['isInjectable']);
        $this->assertSame('\\PSFS\\base\\Router', $fromArray['class']);
        $this->assertFalse($fromArray['singleton']);
        $this->assertFalse($fromArray['required']);
        $this->assertSame('cache', $fromArray['source']);
    }

    public function testExtractVariablesHandlesPropertiesWithoutExplicitVarType(): void
    {
        $reflector = new ReflectionClass(InjectorVariableWithoutTypeExample::class);
        $variables = InjectorHelper::extractVariables($reflector);

        $this->assertArrayHasKey('publicWithoutType', $variables);
        $this->assertSame('string', $variables['publicWithoutType']['type'] ?? null);
    }

    public function testExtractVariablesIncludesEnumWhenValuesAreDeclared(): void
    {
        $reflector = new ReflectionClass(InjectorVariableWithValuesExample::class);
        $variables = InjectorHelper::extractVariables($reflector);

        $this->assertArrayHasKey('status', $variables);
        $this->assertSame(['draft', 'published'], $variables['status']['enum'] ?? null);
    }

    public function testExtractPropertiesThrowsWhenInjectableClassIsMissing(): void
    {
        $configBackup = Config::getInstance()->dumpConfig();
        try {
            $override = $configBackup;
            $override['metadata.attributes.enabled'] = true;
            Config::save($override, []);
            Config::getInstance()->loadConfigData(true);

            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('cannot be empty');
            InjectorHelper::extractProperties(new ReflectionClass(InvalidInjectableClassExample::class));
        } finally {
            Config::save($configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
    }

    public function testExtractPropertiesCanFilterByPrivateVisibility(): void
    {
        $properties = InjectorHelper::extractProperties(
            new ReflectionClass(PrivateInjectableDocExample::class),
            ReflectionProperty::IS_PRIVATE,
            '/@Injectable/im'
        );

        $this->assertArrayHasKey('security', $properties);
        $this->assertSame('\\PSFS\\base\\Security', $properties['security']);
    }

    public function testGetValuesAndDefaultValueFromDoc(): void
    {
        $reflector = new ReflectionClass(InjectorVariableWithValuesExample::class);
        $property = $reflector->getProperty('status');
        $doc = (string)$property->getDocComment();

        $this->assertSame(['draft', 'published'], InjectorHelper::getValues($doc, $property));
        $this->assertSame('draft', InjectorHelper::getDefaultValue($doc, $property));
    }

    public function testExtractPropertiesReturnsEmptyWhenVisibilityFilterDoesNotMatch(): void
    {
        $reflector = new ReflectionClass(ParentInjectableExample::class);

        $publicProps = InjectorHelper::extractProperties($reflector, ReflectionProperty::IS_PUBLIC);
        $privateProps = InjectorHelper::extractProperties($reflector, ReflectionProperty::IS_PRIVATE);

        $this->assertSame([], $publicProps);
        $this->assertSame([], $privateProps);
    }

    public function testCheckIsRequiredResolvesMetadataTagBeforeRegexFallback(): void
    {
        $reflector = new ReflectionClass(RequiredDocPropertyExample::class);
        $property = $reflector->getProperty('field');
        $doc = (string)$property->getDocComment();

        $this->assertTrue(InjectorHelper::checkIsRequired($doc, $property));
        $this->assertFalse(InjectorHelper::checkIsRequired('', null));
    }

    public function testGetValuesReturnsNullWhenMetadataHasNoValues(): void
    {
        $reflector = new ReflectionClass(InjectorVariableWithoutValuesExample::class);
        $property = $reflector->getProperty('title');
        $doc = (string)$property->getDocComment();

        $this->assertSame('', InjectorHelper::getValues($doc, $property));
    }

    public function testExtractVariablesSkipsFieldsWithoutResolvableType(): void
    {
        $reflector = new ReflectionClass(InjectorVariableUnresolvableTypeExample::class);
        $variables = InjectorHelper::extractVariables($reflector);

        $this->assertArrayNotHasKey('raw', $variables);
    }

    public function testGetValuesSupportsAttributeArrayValues(): void
    {
        $configBackup = Config::getInstance()->dumpConfig();
        try {
            $override = $configBackup;
            $override['metadata.attributes.enabled'] = true;
            Config::save($override, []);
            Config::getInstance()->loadConfigData(true);

            $reflector = new ReflectionClass(InjectorVariableWithArrayValuesAttributeExample::class);
            $property = $reflector->getProperty('status');
            $doc = (string)$property->getDocComment();

            $this->assertSame(['alpha', 'beta'], InjectorHelper::getValues($doc, $property));
        } finally {
            Config::save($configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
    }

    public function testResolveInjectableRuntimeDefinitionFallsBackWhenPropertyIsNotInjectable(): void
    {
        $definition = InjectorHelper::resolveInjectableRuntimeDefinition(
            RuntimeInjectableDefinitionExample::class,
            'notInjectable',
            '\\PSFS\\base\\Security'
        );

        $this->assertTrue($definition['isInjectable']);
        $this->assertSame('\\PSFS\\base\\Security', $definition['class']);
        $this->assertSame('cache', $definition['source']);
    }

    public function testGetClassPropertiesIncludesParentDefinitions(): void
    {
        $properties = InjectorHelper::getClassProperties(ChildInjectableExample::class);
        $this->assertArrayHasKey('security', $properties);
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

    protected string $notInjectable = 'x';
}

class InjectorVariableWithoutTypeExample
{
    /** Just docs without @var */
    public $publicWithoutType;
}

class InjectorVariableWithValuesExample
{
    /**
     * @var string
     * @values draft|published
     * @default draft
     */
    public string $status = 'draft';
}

class InvalidInjectableClassExample
{
    #[Injectable(class: '')]
    protected $security;
}

class PrivateInjectableDocExample
{
    /**
     * @Injectable
     * @var \PSFS\base\Security
     */
    private $security;
}

class ParentInjectableExample
{
    /**
     * @Injectable
     * @var \PSFS\base\Security
     */
    protected $security;
}

class ChildInjectableExample extends ParentInjectableExample
{
}

class InjectorVariableWithArrayValuesAttributeExample
{
    #[Values(['alpha', 'beta'])]
    public string $status = 'alpha';
}

class RequiredDocPropertyExample
{
    /**
     * @var string
     * @required true
     */
    public string $field = 'x';
}

class InjectorVariableWithoutValuesExample
{
    /**
     * @var string
     */
    public string $title = 'demo';
}

class InjectorVariableUnresolvableTypeExample
{
    public $raw;
}
