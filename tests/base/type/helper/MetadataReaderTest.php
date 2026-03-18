<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\types\helpers\MetadataReader;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\Label;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\VarType;

class MetadataReaderTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
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
        $method = new \ReflectionMethod(MetadataReaderDocExample::class, 'routeFromDoc');
        $doc = (string)$method->getDocComment();

        $value = MetadataReader::getTagValue('route', $doc, null, $method);

        $this->assertSame('/doc-route', $value);
    }

    public function testHasInjectableDetectsAttributeAndAnnotation(): void
    {
        $this->setAttributesEnabled(true);

        $attrProperty = new \ReflectionProperty(MetadataReaderAttributeExample::class, 'withInjectableAttribute');
        $this->assertTrue(MetadataReader::hasInjectable($attrProperty, (string)$attrProperty->getDocComment()));

        $docProperty = new \ReflectionProperty(MetadataReaderDocExample::class, 'withInjectableDoc');
        $this->assertTrue(MetadataReader::hasInjectable($docProperty, (string)$docProperty->getDocComment()));
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

    private function setAttributesEnabled(bool $enabled): void
    {
        $config = $this->configBackup;
        $config['metadata.attributes.enabled'] = $enabled;
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
    #[Injectable]
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

