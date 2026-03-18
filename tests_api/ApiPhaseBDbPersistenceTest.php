<?php

namespace PSFS\apitests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PSFS\apitests\support\ClientModuleHarness;

#[Group('api')]
#[Group('api-phase-b-db')]
#[Group('api-mysql')]
class ApiPhaseBDbPersistenceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClientModuleHarness::acquire();
    }

    public static function tearDownAfterClass(): void
    {
        ClientModuleHarness::release();
    }

    protected function setUp(): void
    {
        ClientModuleHarness::resetSeedData();
    }

    public function testGeneratedQueriesReadSeededRecords(): void
    {
        $test = \CLIENT\Models\Test\TestQuery::create()->findPk(1);
        $this->assertNotNull($test);
        $this->assertSame(100, $test->getNumber());
        $this->assertSame('DEV', $test->getType());

        $related = $test->getRelated();
        $this->assertNotNull($related);
        $this->assertSame('Related fixture', $related->getTitle());
    }

    public function testGeneratedModelsPersistAndRelateData(): void
    {
        $related = new \CLIENT\Models\Related\Related();
        $related->setTitle('Related runtime');
        $related->save();

        $test = new \CLIENT\Models\Test\Test();
        $test->setNumber(210);
        $test->setSummary('Runtime summary');
        $test->setType('QA');
        $test->setChecker(true);
        $test->setIdRelated($related->getIdRelated());
        $test->save();

        $fetched = \CLIENT\Models\Test\TestQuery::create()->findPk($test->getId());
        $this->assertNotNull($fetched);
        $this->assertSame(210, $fetched->getNumber());
        $this->assertSame($related->getIdRelated(), $fetched->getIdRelated());
    }
}
