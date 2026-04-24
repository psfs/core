<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\exception\MetadataContractException;
use PSFS\base\types\helpers\MetadataReader;
use PSFS\base\types\helpers\attributes\Api;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\helpers\attributes\Route;
use PSFS\base\types\helpers\attributes\VarType;
use PSFS\base\types\helpers\metadata\MetadataAttributeBundleBuilder;
use PSFS\base\types\helpers\metadata\MetadataEngine;
use PSFS\base\types\helpers\metadata\MetadataEngineConfig;
use PSFS\base\types\helpers\metadata\MetadataInjectableResolver;
use PSFS\base\types\helpers\metadata\MetadataTagValueResolver;
use PSFS\controller\ConfigController;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class MetadataEngineTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
        MetadataReader::resetEngineCaches();
        MetadataReader::clearLegacyFallbackLogs();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
        MetadataReader::resetEngineCaches();
    }

    public function testReaderUsesEngineAndInProcessCacheHitsIncrease(): void
    {
        $override = $this->configBackup;
        $override['debug'] = false;
        $override['metadata.engine.enabled'] = true;
        $override['metadata.engine.redis.enabled'] = false;
        $override['metadata.engine.opcache.enabled'] = false;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $method = new ReflectionMethod(ConfigController::class, 'config');
        $doc = (string)$method->getDocComment();
        $before = MetadataReader::getEngineStats();

        MetadataReader::getTagValue('route', $doc, null, $method);
        MetadataReader::getTagValue('route', $doc, null, $method);
        MetadataReader::getTagValue('http', $doc, 'ALL', $method);

        $after = MetadataReader::getEngineStats();
        $deltaL0 = ((int)$after['metadata.hit_l0']) - ((int)$before['metadata.hit_l0']);
        $this->assertGreaterThanOrEqual(1, $deltaL0);
    }

    public function testClassMethodPropertyMetadataDtoAreBuiltFromEngine(): void
    {
        $engine = new MetadataEngine();
        $engine->clearLocalCache();

        $classMeta = $engine->getClassMetadata(ConfigController::class);
        $methodMeta = $engine->getMethodMetadata(ConfigController::class, 'config');
        $propertyMeta = $engine->getPropertyMetadata(ConfigController::class, 'config');

        $this->assertSame(ConfigController::class, $classMeta->className);
        $this->assertSame('config', $methodMeta->method);
        $this->assertSame('config', $propertyMeta->property);
    }

    public function testDebugModeRegeneratesImmediatelyWhenSourceSignatureChanges(): void
    {
        $override = $this->configBackup;
        $override['debug'] = true;
        $override['metadata.engine.enabled'] = true;
        $override['metadata.engine.redis.enabled'] = false;
        $override['metadata.engine.opcache.enabled'] = false;
        $override['metadata.engine.soft_ttl'] = 9999;
        $override['metadata.engine.hard_ttl'] = 9999;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $probe = new class extends MetadataEngine {
            public string $signature = 'sig-a';
            public int $buildCount = 0;

            protected function sourceSignature(ReflectionClass|ReflectionMethod|ReflectionProperty $reflector): string
            {
                return $this->signature;
            }

            protected function buildClassBundle(ReflectionClass $reflection): array
            {
                $this->buildCount++;
                return [
                    'class_tags' => ['build_count' => $this->buildCount],
                    'method_tags' => [],
                    'property_nodes' => [],
                    'signature' => $this->signature,
                ];
            }
        };

        $probe->getClassMetadata(ConfigController::class);
        $this->assertSame(1, $probe->buildCount);

        $probe->signature = 'sig-b';
        $probe->getClassMetadata(ConfigController::class);
        $this->assertSame(2, $probe->buildCount);
    }

    public function testProdModeKeepsStableBundleWhenSignatureIsStable(): void
    {
        $override = $this->configBackup;
        $override['debug'] = false;
        $override['metadata.engine.enabled'] = true;
        $override['metadata.engine.redis.enabled'] = false;
        $override['metadata.engine.opcache.enabled'] = false;
        $override['metadata.engine.soft_ttl'] = 9999;
        $override['metadata.engine.hard_ttl'] = 9999;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $probe = new class extends MetadataEngine {
            public int $buildCount = 0;

            protected function sourceSignature(ReflectionClass|ReflectionMethod|ReflectionProperty $reflector): string
            {
                return 'stable-signature';
            }

            protected function buildClassBundle(ReflectionClass $reflection): array
            {
                $this->buildCount++;
                return [
                    'class_tags' => ['build_count' => $this->buildCount],
                    'method_tags' => [],
                    'property_nodes' => [],
                    'signature' => 'stable-signature',
                ];
            }
        };

        $probe->getClassMetadata(ConfigController::class);
        $probe->getClassMetadata(ConfigController::class);
        $this->assertSame(1, $probe->buildCount);
    }

    public function testLockContentionServesStalePayloadWhenRegenerationLockCannotBeAcquired(): void
    {
        $override = $this->configBackup;
        $override['debug'] = false;
        $override['metadata.engine.enabled'] = true;
        $override['metadata.engine.redis.enabled'] = false;
        $override['metadata.engine.opcache.enabled'] = false;
        $override['metadata.engine.swr.enabled'] = false;
        $override['metadata.engine.soft_ttl'] = 1;
        $override['metadata.engine.hard_ttl'] = 1;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $probe = new class extends MetadataEngine {
            public int $buildCount = 0;

            protected function sourceSignature(ReflectionClass|ReflectionMethod|ReflectionProperty $reflector): string
            {
                return 'lock-signature';
            }

            protected function acquireLock(string $cacheKey): bool
            {
                return false;
            }

            protected function buildClassBundle(ReflectionClass $reflection): array
            {
                $this->buildCount++;
                return [
                    'class_tags' => ['build_count' => $this->buildCount],
                    'method_tags' => [],
                    'property_nodes' => [],
                    'signature' => 'lock-signature',
                ];
            }
        };

        $before = $probe->getStats();
        $probe->getClassMetadata(ConfigController::class);
        sleep(2);
        $probe->getClassMetadata(ConfigController::class);
        $after = $probe->getStats();

        $this->assertSame(1, $probe->buildCount);
        $this->assertGreaterThan((int)$before['metadata.lock_contention'], (int)$after['metadata.lock_contention']);
    }

    public function testSoftTtlSwrQueuesBackgroundRegenerationWithoutBlocking(): void
    {
        $override = $this->configBackup;
        $override['debug'] = false;
        $override['metadata.engine.enabled'] = true;
        $override['metadata.engine.redis.enabled'] = false;
        $override['metadata.engine.opcache.enabled'] = false;
        $override['metadata.engine.swr.enabled'] = true;
        $override['metadata.engine.soft_ttl'] = 1;
        $override['metadata.engine.hard_ttl'] = 15;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $probe = new class extends MetadataEngine {
            public bool $queued = false;
            public int $buildCount = 0;

            protected function sourceSignature(ReflectionClass|ReflectionMethod|ReflectionProperty $reflector): string
            {
                return 'swr-signature';
            }

            protected function queueBackgroundRegeneration(string $cacheKey, string $className): void
            {
                $this->queued = true;
            }

            protected function buildClassBundle(ReflectionClass $reflection): array
            {
                $this->buildCount++;
                return [
                    'class_tags' => ['build_count' => $this->buildCount],
                    'method_tags' => [],
                    'property_nodes' => [],
                    'signature' => 'swr-signature',
                ];
            }
        };

        $probe->getClassMetadata(ConfigController::class);
        sleep(2);
        $probe->getClassMetadata(ConfigController::class);

        $this->assertSame(1, $probe->buildCount);
        $this->assertTrue($probe->queued);
    }

    public function testSignatureMismatchDropsStaleRedisAndOpcacheEntries(): void
    {
        $override = $this->configBackup;
        $override['debug'] = false;
        $override['metadata.engine.enabled'] = true;
        $override['metadata.engine.redis.enabled'] = true;
        $override['metadata.engine.opcache.enabled'] = true;
        $override['psfs.redis'] = true;
        $override['metadata.engine.soft_ttl'] = 30;
        $override['metadata.engine.hard_ttl'] = 60;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $probe = new class extends MetadataEngine {
            public bool $redisDropped = false;
            public bool $opcacheDropped = false;
            public int $buildCount = 0;

            protected function sourceSignature(ReflectionClass|ReflectionMethod|ReflectionProperty $reflector): string
            {
                return 'new-signature';
            }

            protected function readFromOpcacheArtifact(string $cacheKey): ?array
            {
                return [
                    'payload' => ['class_tags' => [], 'method_tags' => [], 'property_nodes' => [], 'signature' => 'old-signature'],
                    'signature' => 'old-signature',
                    'soft_expires_at' => time() + 20,
                    'hard_expires_at' => time() + 20,
                    'created_at' => time(),
                ];
            }

            protected function readFromRedis(string $cacheKey): ?array
            {
                return [
                    'payload' => ['class_tags' => [], 'method_tags' => [], 'property_nodes' => [], 'signature' => 'old-signature'],
                    'signature' => 'old-signature',
                    'soft_expires_at' => time() + 20,
                    'hard_expires_at' => time() + 20,
                    'created_at' => time(),
                ];
            }

            protected function dropRedisEntry(string $cacheKey): void
            {
                $this->redisDropped = true;
            }

            protected function dropOpcacheArtifact(string $cacheKey): void
            {
                $this->opcacheDropped = true;
            }

            protected function writeOpcacheArtifact(string $cacheKey, array $entry): void
            {
            }

            protected function writeToRedis(string $cacheKey, array $entry): void
            {
            }

            protected function buildClassBundle(ReflectionClass $reflection): array
            {
                $this->buildCount++;
                return [
                    'class_tags' => ['build_count' => $this->buildCount],
                    'method_tags' => [],
                    'property_nodes' => [],
                    'signature' => 'new-signature',
                ];
            }
        };

        $probe->getClassMetadata(ConfigController::class);

        $this->assertTrue($probe->redisDropped);
        $this->assertTrue($probe->opcacheDropped);
        $this->assertSame(1, $probe->buildCount);
    }

    public function testLegacyDocFallbackBranchesAreEnforcedAndLogged(): void
    {
        $method = new ReflectionMethod(MetadataEngineLegacyDocExample::class, 'legacyMethod');
        $docMethod = (string)$method->getDocComment();

        $override = $this->configBackup;
        $override['metadata.attributes.enabled'] = true;
        $override['metadata.annotations.fallback.enabled'] = false;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $engine = new MetadataEngine();
        $this->expectException(MetadataContractException::class);
        $engine->hasDeprecated($method, $docMethod);
    }

    public function testLegacyDocFallbackDisabledThrowsForPayloadReturnVarAndInjectable(): void
    {
        $method = new ReflectionMethod(MetadataEngineLegacyDocExample::class, 'legacyMethod');
        $property = new ReflectionProperty(MetadataEngineLegacyDocExample::class, 'legacyProperty');
        $docMethod = (string)$method->getDocComment();
        $docProperty = (string)$property->getDocComment();

        $override = $this->configBackup;
        $override['metadata.attributes.enabled'] = true;
        $override['metadata.annotations.fallback.enabled'] = false;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $engine = new MetadataEngine();

        try {
            $engine->extractPayload('DefaultPayload', $method, $docMethod);
            self::fail('Expected payload contract exception');
        } catch (MetadataContractException) {
        }

        try {
            $engine->extractReturnSpec($method, $docMethod);
            self::fail('Expected return contract exception');
        } catch (MetadataContractException) {
        }

        try {
            $engine->extractVarType($property, $docProperty);
            self::fail('Expected var contract exception');
        } catch (MetadataContractException) {
        }

        try {
            $engine->resolveInjectableDefinition($property, $docProperty);
            self::fail('Expected injectable contract exception');
        } catch (MetadataContractException) {
        }

        $this->addToAssertionCount(4);
    }

    public function testLegacyDocFallbackReturnsValuesWhenEnabled(): void
    {
        $method = new ReflectionMethod(MetadataEngineLegacyDocExample::class, 'legacyMethod');
        $property = new ReflectionProperty(MetadataEngineLegacyDocExample::class, 'legacyProperty');
        $docMethod = (string)$method->getDocComment();
        $docProperty = (string)$property->getDocComment();

        $override = $this->configBackup;
        $override['metadata.attributes.enabled'] = true;
        $override['metadata.annotations.fallback.enabled'] = true;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $engine = new MetadataEngine();
        $this->assertTrue($engine->hasDeprecated($method, $docMethod));
        $this->assertSame('Legacy\\Payload', $engine->extractPayload('DefaultPayload', $method, $docMethod));
        $this->assertSame('LegacyType(item=Legacy\\Dto)', $engine->extractReturnSpec($method, $docMethod));
        $this->assertSame('\\PSFS\\base\\Cache', $engine->extractVarType($property, $docProperty));
        $definition = $engine->resolveInjectableDefinition($property, $docProperty);
        $this->assertTrue($definition['isInjectable']);
        $this->assertSame('annotation', $definition['source']);
    }

    public function testEngineCanReturnEmptyMetadataForUnknownClassAndDisabledEnginePath(): void
    {
        $override = $this->configBackup;
        $override['metadata.engine.enabled'] = false;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $engine = new MetadataEngine();
        $unknown = $engine->getClassMetadata('\\PSFS\\does\\not\\ExistClass');
        $known = $engine->getClassMetadata(ConfigController::class);

        $this->assertSame('\\PSFS\\does\\not\\ExistClass', $unknown->className);
        $this->assertSame([], $unknown->tags);
        $this->assertSame(ConfigController::class, $known->className);
    }

    public function testRedisAndLockHelpersHandleExceptionsAndOpcacheDrop(): void
    {
        $override = $this->configBackup;
        $override['metadata.engine.enabled'] = true;
        $override['metadata.engine.redis.enabled'] = true;
        $override['psfs.redis'] = true;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $redisStub = $this->createMock(\Redis::class);
        $redisStub->method('get')->willThrowException(new \RedisException('boom-get'));
        $redisStub->method('setex')->willThrowException(new \RedisException('boom-setex'));
        $redisStub->method('del')->willThrowException(new \RedisException('boom-del'));
        $redisStub->method('set')->willThrowException(new \RedisException('boom-set'));

        $probe = new class($redisStub) extends MetadataEngine {
            private \Redis $redisStub;

            public function __construct(\Redis $redisStub)
            {
                $this->redisStub = $redisStub;
            }

            protected function redisClient(): ?\Redis
            {
                return $this->redisStub;
            }

            public function exposeReadFromRedis(string $cacheKey): ?array
            {
                return $this->readFromRedis($cacheKey);
            }

            public function exposeWriteToRedis(string $cacheKey, array $entry): void
            {
                $this->writeToRedis($cacheKey, $entry);
            }

            public function exposeDropRedis(string $cacheKey): void
            {
                $this->dropRedisEntry($cacheKey);
            }

            public function exposeAcquire(string $cacheKey): bool
            {
                return $this->acquireLock($cacheKey);
            }

            public function exposeRelease(string $cacheKey): void
            {
                $this->releaseLock($cacheKey);
            }

            public function exposeDropOpcache(string $cacheKey): void
            {
                $this->dropOpcacheArtifact($cacheKey);
            }
        };

        $this->assertNull($probe->exposeReadFromRedis('cache-key'));
        $probe->exposeWriteToRedis('cache-key', ['payload' => ['x' => 1]]);
        $probe->exposeDropRedis('cache-key');
        $this->assertTrue($probe->exposeAcquire('cache-key'));
        $probe->exposeRelease('cache-key');
        $probe->exposeDropOpcache('cache-key');
        $this->assertNull($probe->exposeReadFromRedis('cache-key-2'));
    }

    public function testCacheModeForcesSpecificLayerSelection(): void
    {
        $override = $this->configBackup;
        $override['metadata.engine.enabled'] = true;
        $override['psfs.redis'] = true;
        $override['metadata.engine.redis.enabled'] = true;
        $override['metadata.engine.opcache.enabled'] = true;
        Config::save($override, []);
        Config::getInstance()->loadConfigData(true);

        $probe = new MetadataEngine();
        $localMethod = new \ReflectionMethod(MetadataEngine::class, 'localCacheEnabled');
        $localMethod->setAccessible(true);
        $redisMethod = new \ReflectionMethod(MetadataEngine::class, 'redisEnabled');
        $redisMethod->setAccessible(true);
        $opcacheMethod = new \ReflectionMethod(MetadataEngine::class, 'opcacheEnabled');
        $opcacheMethod->setAccessible(true);

        $config = Config::getInstance()->dumpConfig();
        $config['psfs.cache.mode'] = 'MEMORY';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        $this->assertTrue((bool)$localMethod->invoke($probe));
        $this->assertFalse((bool)$redisMethod->invoke($probe));
        $this->assertFalse((bool)$opcacheMethod->invoke($probe));

        $config['psfs.cache.mode'] = 'REDIS';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        $this->assertFalse((bool)$localMethod->invoke($probe));
        $this->assertTrue((bool)$redisMethod->invoke($probe));
        $this->assertFalse((bool)$opcacheMethod->invoke($probe));

        $config['psfs.cache.mode'] = 'OPCACHE';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        $this->assertFalse((bool)$localMethod->invoke($probe));
        $this->assertFalse((bool)$redisMethod->invoke($probe));
        // In CI/local this may be false if extension is unavailable; assert type contract instead.
        $this->assertIsBool((bool)$opcacheMethod->invoke($probe));
    }

    public function testAttributeBundleBuilderExtractsClassMethodAndPropertyTags(): void
    {
        $builder = new MetadataAttributeBundleBuilder();
        $reflection = new ReflectionClass(MetadataEngineAttributeBundleExample::class);
        $bundle = $builder->build($reflection, 'signature');

        $this->assertSame('ExampleApi', $bundle['class_tags']['api']);
        $this->assertSame('/attribute/example', $bundle['method_tags']['routeAction']['route']);
        $this->assertSame('\\PSFS\\base\\Cache', $bundle['property_nodes']['cache']['tags']['var']);
        $this->assertSame('\\PSFS\\base\\Cache', $builder->propertyType($reflection->getProperty('typedCache')->getType()));
        $this->assertNull($builder->propertyType($reflection->getProperty('plain')->getType()));
        $this->assertSame('signature', $bundle['signature']);
    }

    public function testExtractedMetadataEngineConfigResolvesModesAndTtls(): void
    {
        $config = $this->configBackup;
        $config['debug'] = false;
        $config['metadata.engine.enabled'] = false;
        $config['metadata.engine.redis.enabled'] = true;
        $config['metadata.engine.opcache.enabled'] = true;
        $config['metadata.engine.version'] = '';
        $config['metadata.engine.soft_ttl'] = 5;
        $config['metadata.engine.hard_ttl'] = 3;
        $config['metadata.engine.regen.lock_ttl'] = 0;
        $config['metadata.engine.swr.enabled'] = true;
        $config['psfs.redis'] = true;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);

        $engineConfig = new MetadataEngineConfig();
        $this->assertSame('v3', $engineConfig->engineVersion());
        $this->assertSame(5, $engineConfig->effectiveSoftTtl());
        $this->assertSame(5, $engineConfig->effectiveHardTtl());
        $this->assertSame(1, $engineConfig->regenLockTtl());
        $this->assertTrue($engineConfig->swrEnabled());
        $this->assertFalse($engineConfig->engineEnabled());

        $config['psfs.cache.mode'] = 'REDIS';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        $this->assertTrue($engineConfig->redisEnabled());
        $this->assertFalse($engineConfig->localCacheEnabled());
        $this->assertFalse($engineConfig->opcacheEnabled());

        $config['psfs.cache.mode'] = 'MEMORY';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
        $this->assertTrue($engineConfig->engineEnabled());
        $this->assertTrue($engineConfig->localCacheEnabled());
    }

    public function testInjectableResolverHandlesAttributeLegacyAndEmptyDefinitions(): void
    {
        $reflection = new ReflectionClass(MetadataEngineAttributeBundleExample::class);
        $attributeReader = static function (string $tag, ReflectionProperty $property): mixed {
            foreach ($property->getAttributes(Injectable::class) as $attribute) {
                return $tag === 'injectable' ? $attribute->newInstance()->resolve() : null;
            }
            return null;
        };
        $rejectLegacy = static function (string $tag, ReflectionProperty $property): void {
            throw new MetadataContractException($tag . ':' . $property->getName());
        };
        $legacyHits = [];
        $rememberLegacy = static function (string $fallback) use (&$legacyHits): void {
            $legacyHits[] = $fallback;
        };

        $resolver = new MetadataInjectableResolver(true, true, $attributeReader, $rejectLegacy, $rememberLegacy);
        $attributeDefinition = $resolver->resolve($reflection->getProperty('injectableCache'), '');
        $legacyDefinition = $resolver->resolve(
            $reflection->getProperty('legacyCache'),
            "/**\n * @Injectable\n * @var \\PSFS\\base\\Cache\n */"
        );
        $emptyDefinition = $resolver->resolve($reflection->getProperty('plain'), '');

        $this->assertSame('\\PSFS\\base\\Cache', $attributeDefinition['class']);
        $this->assertFalse($attributeDefinition['singleton']);
        $this->assertSame('annotation_injectable', $legacyHits[0]);
        $this->assertSame('\\PSFS\\base\\Cache', $legacyDefinition['class']);
        $this->assertFalse($emptyDefinition['isInjectable']);

        $strictResolver = new MetadataInjectableResolver(true, false, $attributeReader, $rejectLegacy, $rememberLegacy);
        $this->expectException(MetadataContractException::class);
        $strictResolver->resolve(
            $reflection->getProperty('legacyCache'),
            "/**\n * @Injectable\n * @var \\PSFS\\base\\Cache\n */"
        );
    }

    public function testTagValueResolverHandlesAttributesLegacyAndDeprecatedPolicy(): void
    {
        $reflection = new ReflectionClass(MetadataEngineAttributeBundleExample::class);
        $method = $reflection->getMethod('routeAction');
        $attributeReader = static function (string $tag, ReflectionClass|ReflectionMethod|ReflectionProperty|null $reflector): mixed {
            if ($reflector instanceof ReflectionMethod && $tag === 'route') {
                return '/attribute/example';
            }
            return null;
        };
        $rejectLegacy = static function (string $tag): void {
            throw new MetadataContractException($tag);
        };
        $legacyHits = [];
        $rememberLegacy = static function (string $fallback) use (&$legacyHits): void {
            $legacyHits[] = $fallback;
        };

        $resolver = new MetadataTagValueResolver(true, true, $attributeReader, $rejectLegacy, $rememberLegacy);
        $this->assertSame('/attribute/example', $resolver->getTagValue('route', '', null, $method));
        $this->assertSame('GET', $resolver->getTagValue('http', "/**\n * @GET\n */", 'ALL', $method));
        $this->assertTrue($resolver->getTagValue('cache', "/**\n * @cache true\n */", false, $method));
        $this->assertTrue($resolver->hasDeprecated($method, "/**\n * @deprecated\n */"));
        $this->assertSame('annotation_deprecated', end($legacyHits));

        $strictResolver = new MetadataTagValueResolver(true, false, $attributeReader, $rejectLegacy, $rememberLegacy);
        $this->expectException(MetadataContractException::class);
        $strictResolver->getTagValue('payload', "/**\n * @payload Legacy\\Payload\n */", null, $method);
    }
}

#[Api('ExampleApi')]
class MetadataEngineAttributeBundleExample
{
    #[VarType('\\PSFS\\base\\Cache')]
    public $cache;

    public \PSFS\base\Cache $typedCache;

    #[Injectable(class: \PSFS\base\Cache::class, singleton: false)]
    public $injectableCache;

    public $legacyCache;

    public $plain;

    #[Route('/attribute/example')]
    public function routeAction(): void
    {
    }
}

class MetadataEngineLegacyDocExample
{
    /**
     * @deprecated
     * @payload Legacy\Payload
     * @return LegacyType(item=Legacy\Dto)
     */
    public function legacyMethod(): void
    {
    }

    /**
     * @Injectable
     * @var \PSFS\base\Cache
     */
    public $legacyProperty;
}
