<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\Security;
use PSFS\base\types\SimpleService;
use PSFS\base\types\traits\Api\Crud\ApiListTrait;
use PSFS\base\types\traits\Router\ModulesTrait;
use PSFS\base\types\traits\TemplateTrait;
use PSFS\base\config\Config;
use PSFS\base\exception\MetadataContractException;
use PSFS\base\types\helpers\MetadataReader;
use PSFS\base\types\helpers\attributes\Action;
use PSFS\base\types\helpers\attributes\ApiDeprecated;
use PSFS\base\types\helpers\attributes\Api;
use PSFS\base\types\helpers\attributes\ApiReturn;
use PSFS\base\types\helpers\attributes\Cacheable;
use PSFS\base\types\helpers\attributes\DefaultValue;
use PSFS\base\types\helpers\attributes\HttpMethod;
use PSFS\base\types\helpers\attributes\Icon;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Required;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\Payload;
use PSFS\base\types\helpers\attributes\VarType;
use PSFS\base\types\helpers\attributes\Values;
use PSFS\base\types\helpers\attributes\Visible;

class MetadataReaderTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        MetadataReader::clearLegacyFallbackLogs();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        MetadataReader::clearLegacyFallbackLogs();
    }

    public function testGetTagValueUsesDocWhenAttributesDisabled(): void
    {
        $this->setAttributesEnabled(false);
        $method = new \ReflectionMethod(MetadataReaderDocExample::class, 'routeFromDoc');
        $doc = (string)$method->getDocComment();

        $value = MetadataReader::getTagValue('route', $doc, null, $method);

        $this->assertSame('/doc-route', $value);
    }

    public function testGetTagValuePrefersAttributesWhenEnabled(): void
    {
        $this->setAttributesEnabled(true);
        $method = new \ReflectionMethod(MetadataReaderAttributeExample::class, 'routeFromAttribute');
        $doc = (string)$method->getDocComment();

        $value = MetadataReader::getTagValue('route', $doc, null, $method);

        $this->assertSame('/attr-route', $value);
    }

    public function testGetTagValueFallsBackToDocWhenAttributeMissing(): void
    {
        $this->setAttributesEnabled(true);
        $this->setAnnotationsFallbackEnabled(true);
        $method = new \ReflectionMethod(MetadataReaderDocExample::class, 'routeFromDoc');
        $doc = (string)$method->getDocComment();

        $value = MetadataReader::getTagValue('route', $doc, null, $method);

        $this->assertSame('/doc-route', $value);
    }

    public function testLegacyFallbackLogIsRegisteredOncePerContext(): void
    {
        $this->setAttributesEnabled(true);
        $this->setAnnotationsFallbackEnabled(true);
        $method = new \ReflectionMethod(MetadataReaderDocExample::class, 'routeFromDoc');
        $doc = (string)$method->getDocComment();

        MetadataReader::getTagValue('route', $doc, null, $method);
        MetadataReader::getTagValue('route', $doc, null, $method);

        $logs = MetadataReader::getLegacyFallbackLogs();
        $matches = array_values(array_filter($logs, static fn ($context) => $context === 'annotation_route'));
        $this->assertCount(1, $matches);
    }

    public function testNoLegacyFallbackLogWhenAttributeResolvesTag(): void
    {
        $this->setAttributesEnabled(true);
        $method = new \ReflectionMethod(MetadataReaderAttributeExample::class, 'routeFromAttribute');
        $doc = (string)$method->getDocComment();

        $value = MetadataReader::getTagValue('route', $doc, null, $method);

        $this->assertSame('/attr-route', $value);
        $this->assertNotContains('annotation_route', MetadataReader::getLegacyFallbackLogs());
    }

    public function testHasInjectableDetectsAttributeAndAnnotation(): void
    {
        $this->setAttributesEnabled(true);

        $attrProperty = new \ReflectionProperty(MetadataReaderAttributeExample::class, 'withInjectableAttribute');
        $this->assertTrue(MetadataReader::hasInjectable($attrProperty, (string)$attrProperty->getDocComment()));

        $docProperty = new \ReflectionProperty(MetadataReaderDocExample::class, 'withInjectableDoc');
        $this->assertTrue(MetadataReader::hasInjectable($docProperty, (string)$docProperty->getDocComment()));
    }

    public function testResolveInjectableDefinitionPrefersAttributeOverAnnotation(): void
    {
        $this->setAttributesEnabled(true);
        $property = new \ReflectionProperty(MetadataReaderAttributeExample::class, 'withInjectableAttribute');
        $doc = (string)$property->getDocComment();

        $definition = MetadataReader::resolveInjectableDefinition($property, $doc);

        $this->assertTrue($definition['isInjectable']);
        $this->assertSame('attribute', $definition['source']);
        $this->assertSame('\\PSFS\\base\\Security', $definition['class']);
    }

    public function testExtractVarTypeUsesAttributePropertyTypeAndDocFallback(): void
    {
        $this->setAttributesEnabled(true);

        $attrProperty = new \ReflectionProperty(MetadataReaderAttributeExample::class, 'withVarTypeAttribute');
        $this->assertSame('\\Vendor\\Package\\InjectedType', MetadataReader::extractVarType($attrProperty, (string)$attrProperty->getDocComment()));

        $typedProperty = new \ReflectionProperty(MetadataReaderAttributeExample::class, 'typedProperty');
        $this->assertSame('\\DateTime', MetadataReader::extractVarType($typedProperty, (string)$typedProperty->getDocComment()));

        $docProperty = new \ReflectionProperty(MetadataReaderDocExample::class, 'withVarDoc');
        $this->assertSame('\\PSFS\\base\\Cache', MetadataReader::extractVarType($docProperty, (string)$docProperty->getDocComment()));
    }

    public function testDocParsersForHttpVisibleCacheAndDefaults(): void
    {
        $this->setAttributesEnabled(false);
        $method = new \ReflectionMethod(MetadataReaderDocExample::class, 'httpVisibleCacheDoc');
        $doc = (string)$method->getDocComment();

        $this->assertSame('POST', MetadataReader::getTagValue('http', $doc, 'ALL', $method));
        $this->assertFalse(MetadataReader::getTagValue('visible', $doc, true, $method));
        $this->assertTrue(MetadataReader::getTagValue('cache', $doc, false, $method));
        $this->assertSame('fallback', MetadataReader::getTagValue('label', null, 'fallback', $method));
    }

    public function testMethodTagParityPrefersAttributesOverAnnotationsWhenEnabled(): void
    {
        $this->setAttributesEnabled(true);
        $method = new \ReflectionMethod(MetadataReaderParityExample::class, 'mixedMethodTags');
        $doc = (string)$method->getDocComment();

        $this->assertSame('/attr-parity', MetadataReader::getTagValue('route', $doc, null, $method));
        $this->assertSame('ATTR_ACTION', MetadataReader::getTagValue('action', $doc, null, $method));
        $this->assertSame('Attribute Label', MetadataReader::getTagValue('label', $doc, null, $method));
        $this->assertSame('fa-attr', MetadataReader::getTagValue('icon', $doc, null, $method));
        $this->assertSame('PATCH', MetadataReader::getTagValue('http', $doc, 'ALL', $method));
        $this->assertFalse(MetadataReader::getTagValue('visible', $doc, true, $method));
        $this->assertFalse(MetadataReader::getTagValue('cache', $doc, true, $method));
    }

    public function testMethodTagParityUsesAnnotationsWhenAttributesDisabled(): void
    {
        $this->setAttributesEnabled(false);
        $this->setAnnotationsFallbackEnabled(true);
        $method = new \ReflectionMethod(MetadataReaderParityExample::class, 'mixedMethodTags');
        $doc = (string)$method->getDocComment();

        $this->assertSame('/doc-parity', MetadataReader::getTagValue('route', $doc, null, $method));
        $this->assertSame('DOC_ACTION', MetadataReader::getTagValue('action', $doc, null, $method));
        $this->assertSame('Doc Label', MetadataReader::getTagValue('label', $doc, null, $method));
        $this->assertSame('fa-doc', MetadataReader::getTagValue('icon', $doc, null, $method));
        $this->assertSame('POST', MetadataReader::getTagValue('http', $doc, 'ALL', $method));
        $this->assertTrue(MetadataReader::getTagValue('visible', $doc, false, $method));
        $this->assertTrue(MetadataReader::getTagValue('cache', $doc, false, $method));
    }

    public function testClassAndPropertyTagParityWithAttributesAndAnnotationFallback(): void
    {
        $this->setAttributesEnabled(true);
        $this->setAnnotationsFallbackEnabled(true);
        $class = new \ReflectionClass(MetadataReaderParityExample::class);
        $classDoc = (string)$class->getDocComment();
        $property = new \ReflectionProperty(MetadataReaderParityExample::class, 'mixedPropertyTags');
        $propertyDoc = (string)$property->getDocComment();

        $this->assertSame('ATTR_API', MetadataReader::getTagValue('api', $classDoc, null, $class));
        $this->assertTrue(MetadataReader::getTagValue('required', $propertyDoc, false, $property));
        $this->assertSame('attr_values', MetadataReader::getTagValue('values', $propertyDoc, null, $property));
        $this->assertSame('attr_default', MetadataReader::getTagValue('default', $propertyDoc, null, $property));

        $fallbackProperty = new \ReflectionProperty(MetadataReaderParityExample::class, 'docOnlyPropertyTags');
        $fallbackDoc = (string)$fallbackProperty->getDocComment();
        $this->assertSame('false', MetadataReader::getTagValue('required', $fallbackDoc, true, $fallbackProperty));
        $this->assertSame('doc_only_values', MetadataReader::getTagValue('values', $fallbackDoc, null, $fallbackProperty));
        $this->assertSame('doc_only_default', MetadataReader::getTagValue('default', $fallbackDoc, null, $fallbackProperty));
    }

    public function testThrowsWhenFallbackDisabledAndLegacyTagPresent(): void
    {
        $this->setAttributesEnabled(true);
        $this->setAnnotationsFallbackEnabled(false);
        $method = new \ReflectionMethod(MetadataReaderDocExample::class, 'routeFromDoc');

        $this->expectException(MetadataContractException::class);
        MetadataReader::getTagValue('route', (string)$method->getDocComment(), null, $method);
    }

    public function testPayloadDeprecatedAndReturnPreferAttributes(): void
    {
        $this->setAttributesEnabled(true);
        $this->setAnnotationsFallbackEnabled(false);
        $method = new \ReflectionMethod(MetadataReaderAttributePayloadExample::class, 'create');

        $this->assertSame('{__API__}', MetadataReader::extractPayload('DefaultModel', $method, (string)$method->getDocComment()));
        $this->assertSame('\PSFS\base\dto\JsonResponse(data={__API__})', MetadataReader::extractReturnSpec($method, (string)$method->getDocComment()));
        $this->assertTrue(MetadataReader::hasDeprecated($method, (string)$method->getDocComment()));
    }

    public function testInjectableParityForCoreMigratedProperties(): void
    {
        $this->setAttributesEnabled(true);

        $simpleServiceLog = new \ReflectionProperty(SimpleService::class, 'log');
        $this->assertTrue(MetadataReader::hasInjectable($simpleServiceLog, (string)$simpleServiceLog->getDocComment()));
        $this->assertSame('\\PSFS\\base\\Logger', MetadataReader::extractVarType($simpleServiceLog, (string)$simpleServiceLog->getDocComment()));

        $templateTraitTpl = new \ReflectionProperty(TemplateTraitHostExample::class, 'tpl');
        $this->assertTrue(MetadataReader::hasInjectable($templateTraitTpl, (string)$templateTraitTpl->getDocComment()));
        $this->assertSame('\\PSFS\\base\\Template', MetadataReader::extractVarType($templateTraitTpl, (string)$templateTraitTpl->getDocComment()));

        $modulesTraitFinder = new \ReflectionProperty(ModulesTraitHostExample::class, 'finder');
        $this->assertTrue(MetadataReader::hasInjectable($modulesTraitFinder, (string)$modulesTraitFinder->getDocComment()));
        $this->assertSame('\\Symfony\\Component\\Finder\\Finder', MetadataReader::extractVarType($modulesTraitFinder, (string)$modulesTraitFinder->getDocComment()));

        $apiListTraitOrder = new \ReflectionProperty(ApiListTraitHostExample::class, 'order');
        $this->assertTrue(MetadataReader::hasInjectable($apiListTraitOrder, (string)$apiListTraitOrder->getDocComment()));
        $this->assertSame('\\PSFS\\base\\dto\\Order', MetadataReader::extractVarType($apiListTraitOrder, (string)$apiListTraitOrder->getDocComment()));
    }

    private function setAttributesEnabled(bool $enabled): void
    {
        $config = $this->configBackup;
        $config['metadata.attributes.enabled'] = $enabled;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
    }

    private function setAnnotationsFallbackEnabled(bool $enabled): void
    {
        $config = Config::getInstance()->dumpConfig();
        $config['metadata.annotations.fallback.enabled'] = $enabled;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
    }
}

class MetadataReaderDocExample
{
    /**
     * @route /doc-route
     */
    public function routeFromDoc(): void
    {
    }

    /**
     * @POST
     * @visible false
     * @cache true
     */
    public function httpVisibleCacheDoc(): void
    {
    }

    /**
     * @Injectable
     * @var \PSFS\base\Security
     */
    private $withInjectableDoc;

    /**
     * @var \PSFS\base\Cache
     */
    private $withVarDoc;
}

class MetadataReaderAttributeExample
{
    #[Injectable(class: Security::class)]
    private $withInjectableAttribute;

    #[VarType('\Vendor\Package\InjectedType')]
    private $withVarTypeAttribute;

    private \DateTime $typedProperty;

    /**
     * @route /doc-route-ignored
     */
    #[Route('/attr-route')]
    #[Label('attr-label')]
    public function routeFromAttribute(): void
    {
    }
}

/**
 * @api DOC_API
 */
#[Api('ATTR_API')]
class MetadataReaderParityExample
{
    /**
     * @route /doc-parity
     * @action DOC_ACTION
     * @label Doc Label
     * @icon fa-doc
     * @POST
     * @visible true
     * @cache true
     */
    #[Route('/attr-parity')]
    #[Action('ATTR_ACTION')]
    #[Label('Attribute Label')]
    #[Icon('fa-attr')]
    #[HttpMethod('PATCH')]
    #[Visible(false)]
    #[Cacheable(false)]
    public function mixedMethodTags(): void
    {
    }

    /**
     * @required false
     * @values doc_values
     * @default doc_default
     */
    #[Required(true)]
    #[Values('attr_values')]
    #[DefaultValue('attr_default')]
    private $mixedPropertyTags;

    /**
     * @required false
     * @values doc_only_values
     * @default doc_only_default
     */
    private $docOnlyPropertyTags;
}

class TemplateTraitHostExample
{
    use TemplateTrait;
}

class ModulesTraitHostExample
{
    use ModulesTrait;
}

abstract class ApiListTraitHostExample
{
    use ApiListTrait;
}

class MetadataReaderAttributePayloadExample
{
    #[Payload('{__API__}')]
    #[ApiReturn('\PSFS\base\dto\JsonResponse(data={__API__})')]
    #[ApiDeprecated(true)]
    public function create(): void
    {
    }
}
