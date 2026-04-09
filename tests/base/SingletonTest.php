<?php

namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\tests\examples\SingletonClassTestExample;

/**
 * Class SingletonTest
 * @package PSFS\tests\base
 */
class SingletonTest extends TestCase
{

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testCompleteSingletonCases()
    {
        $exampleClass = SingletonClassTestExample::getInstance();

        // Basic instance cases
        $this->assertNotNull($exampleClass, 'Error when instance the class');
        $this->assertInstanceOf(Singleton::class, $exampleClass, 'Instance not valid');

        // Singleton pattern cases
        $example2 = SingletonClassTestExample::getInstance();
        $this->assertEquals($exampleClass, $example2, 'Singleton pattern not found');

        // Extended functionality cases
        $this->assertEquals('SingletonClassTestExample', $exampleClass->getShortName(), 'The short name is not equals than expected');
        $exampleClass->init();

        $var = date('Y');
        $exampleClass->fieldTest = $var;
        $this->assertNotNull($exampleClass->fieldTest, 'Assignation for private var not found');
        $this->assertEquals($exampleClass->fieldTest, $var, 'Field has not the same value');

    }

    public function testInjectableAttributeControlsSingletonRuntimeBehavior(): void
    {
        $configBackup = Config::getInstance()->dumpConfig();
        try {
            $override = $configBackup;
            $override['metadata.attributes.enabled'] = true;
            Config::save($override, []);
            Config::getInstance()->loadConfigData(true);

            RuntimeInjectableDependencyProbe::reset();
            RuntimeInjectableSingletonTrueHost::dropInstance();
            RuntimeInjectableSingletonFalseHost::dropInstance();

            $singletonDependency = RuntimeInjectableDependencyProbe::getInstance();

            $singletonHost = RuntimeInjectableSingletonTrueHost::getInstance();
            $this->assertSame($singletonDependency, $singletonHost->getProbe());

            $nonSingletonHost = RuntimeInjectableSingletonFalseHost::getInstance();
            $this->assertNotSame(RuntimeInjectableDependencyProbe::getInstance(), $nonSingletonHost->getProbe());
        } finally {
            Config::save($configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
    }
}

class RuntimeInjectableDependencyProbe
{
    private static ?self $instance = null;
    public string $token;

    public function __construct()
    {
        $this->token = uniqid('probe-', true);
    }

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}

class RuntimeInjectableSingletonTrueHost extends Singleton
{
    #[Injectable(class: RuntimeInjectableDependencyProbe::class, singleton: true)]
    protected $probe;

    public function getProbe(): RuntimeInjectableDependencyProbe
    {
        return $this->probe;
    }
}

class RuntimeInjectableSingletonFalseHost extends Singleton
{
    #[Injectable(class: RuntimeInjectableDependencyProbe::class, singleton: false)]
    protected $probe;

    public function getProbe(): RuntimeInjectableDependencyProbe
    {
        return $this->probe;
    }
}
