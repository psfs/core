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

    public function testEveryGeneratedMigrationCanRoundTripAcrossAllSchemaStates(): void
    {
        $versions = ClientModuleHarness::migrationVersions();
        $this->assertCount(3, $versions);
        $this->assertMigrationState(3, $versions);

        // Exercise every valid directed transition between ordered schema prefixes.
        for ($from = 0; $from <= count($versions); ++$from) {
            if (0 === $from) {
                ClientModuleHarness::rollbackMigrations(0);
            } else {
                ClientModuleHarness::runMigrations($versions[$from - 1]);
            }
            $this->assertMigrationState($from, $versions);

            for ($to = 0; $to <= count($versions); ++$to) {
                if ($to === $from) {
                    continue;
                }

                if ($to > $from) {
                    ClientModuleHarness::runMigrations($versions[$to - 1]);
                } else {
                    ClientModuleHarness::rollbackMigrations(0 === $to ? 0 : $versions[$to - 1]);
                }
                $this->assertMigrationState($to, $versions);

                if ($from > $to) {
                    ClientModuleHarness::runMigrations($versions[$from - 1]);
                } else {
                    ClientModuleHarness::rollbackMigrations(0 === $from ? 0 : $versions[$from - 1]);
                }
                $this->assertMigrationState($from, $versions);
            }
        }

        // Traverse the complete chain from final schema to empty, one migration at a time.
        for ($state = count($versions); $state >= 1; --$state) {
            ClientModuleHarness::rollbackMigrations($state === 1 ? 0 : $versions[$state - 2]);
            $this->assertMigrationState($state - 1, $versions);
        }

        // Rebuild from zero with an explicit Phinx target for each migration.
        for ($state = 1; $state <= count($versions); ++$state) {
            ClientModuleHarness::runMigrations($versions[$state - 1]);
            $this->assertMigrationState($state, $versions);
        }
    }

    /** @param list<int> $versions */
    private function assertMigrationState(int $state, array $versions): void
    {
        $this->assertSame($state, ClientModuleHarness::appliedMigrationCount(), 'Unexpected Phinx ledger state');
        $hasBaseSchema = $state > 0;
        $this->assertSame($hasBaseSchema, ClientModuleHarness::tableExists('CLIENT_TEST'));
        $this->assertSame($hasBaseSchema, ClientModuleHarness::tableExists('CLIENT_TEST_i18n'));
        $this->assertSame($hasBaseSchema, ClientModuleHarness::tableExists('CLIENT_RELATED'));
        $this->assertSame($hasBaseSchema, ClientModuleHarness::tableExists('CLIENT_SOLO_TEST'));
        $hasLabel = $state >= 2;
        $this->assertSame($hasLabel, ClientModuleHarness::columnExists('CLIENT_RELATED', 'MIGRATION_STAGE_LABEL'));
        $this->assertSame($state >= 3, ClientModuleHarness::indexExists('CLIENT_RELATED', 'idx_related_migration_stage_label'));
        if ($hasLabel) {
            $this->assertSame($state >= 3 ? 80 : 40, ClientModuleHarness::columnLength('CLIENT_RELATED', 'MIGRATION_STAGE_LABEL'));
        }
        $this->assertSame(array_slice($versions, 0, $state), ClientModuleHarness::appliedMigrationVersions());
    }

    public function testSeedsRunOnlyWhenExplicitlyRequested(): void
    {
        $this->assertSame(
            0,
            ClientModuleHarness::countRows('CLIENT_RELATED', 'TITLE', 'Phinx seeder fixture')
        );

        ClientModuleHarness::runMigrations();
        $this->assertSame(
            0,
            ClientModuleHarness::countRows('CLIENT_RELATED', 'TITLE', 'Phinx seeder fixture')
        );

        ClientModuleHarness::runSeeders();
        $this->assertSame(
            1,
            ClientModuleHarness::countRows('CLIENT_RELATED', 'TITLE', 'Phinx seeder fixture')
        );
    }
}
