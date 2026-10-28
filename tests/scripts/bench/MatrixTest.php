<?php

namespace PSFS\tests\scripts\bench;

use Psfs\Bench\Matrix;
use Psfs\Bench\Options;

class MatrixTest extends BenchTestCase
{
    private function cells(array $arguments): array
    {
        return iterator_to_array((new Matrix(Options::fromArguments($arguments)))->cells(), false);
    }

    public function testTheTotalMatchesTheNumberOfCells(): void
    {
        foreach ([['--quick'], [], ['--scenarios=page', '--modules=1', '--rows=1,2,3', '--redis=off,sessions,on']] as $arguments) {
            $matrix = new Matrix(Options::fromArguments($arguments));

            self::assertSame($matrix->total(), count(iterator_to_array($matrix->cells(), false)), implode(' ', $arguments));
        }
    }

    public function testEveryCombinationAppearsOnce(): void
    {
        $cells = $this->cells(['--scenarios=api-get', '--modules=2', '--debug=false,true', '--redis=off,on', '--opcache=off,on']);

        self::assertCount(8, $cells);
        self::assertCount(8, array_unique(array_map('json_encode', $cells)));
    }

    public function testOnlyApiScenariosDependOnTheRowCount(): void
    {
        $cells = $this->cells(['--scenarios=page,api-list', '--modules=2', '--rows=10,1000', '--debug=false', '--redis=off', '--opcache=on']);

        $rows = [];
        foreach ($cells as $cell) {
            $rows[$cell['scenario']][] = $cell['rows'];
        }
        self::assertSame([10], $rows['page'], 'The page does not use the table: a single row count');
        self::assertSame([10, 1000], $rows['api-list']);
    }

    public function testTheModuleCountIsTheSlowestDimension(): void
    {
        $cells = $this->cells(['--scenarios=page', '--modules=1,5,9', '--debug=false,true', '--redis=off', '--opcache=off,on']);

        $modules = array_column($cells, 'modules');
        self::assertSame($modules, [1, 1, 1, 1, 5, 5, 5, 5, 9, 9, 9, 9], 'Changing the number of modules regenerates the routes, so cells are grouped by it');
    }

    public function testCellsCarryTypedValues(): void
    {
        $cell = $this->cells(['--scenarios=page', '--modules=3', '--debug=true', '--redis=sessions', '--opcache=jit'])[0];

        self::assertSame(['scenario' => 'page', 'modules' => 3, 'rows' => 1000, 'debug' => true, 'redis' => 'sessions', 'opcache' => 'jit'], $cell);
    }
}
