<?php

namespace PSFS\apitests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PSFS\apitests\support\ClientModuleHarness;

#[Group('api')]
#[Group('api-phase-a-generation')]
class ApiPhaseAGenerationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClientModuleHarness::acquire();
    }

    public static function tearDownAfterClass(): void
    {
        ClientModuleHarness::release();
    }

    public function testGeneratedModuleContainsExpectedArtifacts(): void
    {
        $modulePath = ClientModuleHarness::modulePath();
        $expected = [
            '/Api/Solo.php',
            '/Api/base/SoloBaseApi.php',
            '/Api/Test/Test.php',
            '/Api/Test/base/TestBaseApi.php',
            '/Api/Related/Related.php',
            '/Api/Related/base/RelatedBaseApi.php',
            '/Models/Solo.php',
            '/Models/SoloQuery.php',
            '/Models/Map/SoloTableMap.php',
            '/Models/Test/Test.php',
            '/Models/Test/TestQuery.php',
            '/Models/Test/TestI18n.php',
            '/Models/Test/TestI18nQuery.php',
            '/Models/Test/Map/TestTableMap.php',
            '/Models/Test/Map/TestI18nTableMap.php',
            '/Models/Related/Related.php',
            '/Models/Related/RelatedQuery.php',
            '/Models/Related/Map/RelatedTableMap.php',
            '/Config/config.php',
            '/Config/Sql/CLIENT.sql',
            '/Config/loadDatabase.php',
            '/autoload.php',
        ];
        foreach ($expected as $path) {
            $this->assertFileExists($modulePath . $path, 'Missing generated artifact ' . $path);
        }
    }

    public function testGeneratedSqlContainsRelationsAndI18nTables(): void
    {
        $sqlPath = ClientModuleHarness::modulePath() . '/Config/Sql/CLIENT.sql';
        $sql = file_get_contents($sqlPath);
        $this->assertIsString($sql);
        $this->assertStringContainsString('CLIENT_RELATED', $sql);
        $this->assertStringContainsString('CLIENT_TEST', $sql);
        $this->assertStringContainsString('CLIENT_TEST_i18n', $sql);
        $this->assertStringContainsString('FOREIGN KEY', strtoupper($sql));
    }

    public function testGeneratedClassesAreAutoloadable(): void
    {
        $this->assertTrue(class_exists(\CLIENT\Api\Test\Test::class));
        $this->assertTrue(class_exists(\CLIENT\Models\Test\Test::class));
        $this->assertTrue(class_exists(\CLIENT\Models\Test\TestQuery::class));
        $this->assertTrue(class_exists(\CLIENT\Models\Related\RelatedQuery::class));
    }
}

