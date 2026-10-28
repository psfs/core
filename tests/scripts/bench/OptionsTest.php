<?php

namespace PSFS\tests\scripts\bench;

use PHPUnit\Framework\Attributes\DataProvider;
use Psfs\Bench\Options;

class OptionsTest extends BenchTestCase
{
    public function testDefaultsDescribeTheFullMatrix(): void
    {
        $options = Options::fromArguments([]);

        self::assertSame(['page', 'api-get', 'api-list'], $options->list('scenarios'));
        self::assertSame(['2', '20'], $options->list('modules'));
        self::assertSame(['false', 'true'], $options->list('debug'));
        self::assertSame(['off', 'on'], $options->list('redis'));
        self::assertSame(200, $options->int('iterations'));
        self::assertSame(1, $options->int('repeat'));
    }

    public function testQuickShrinksTheMatrixAndTheSampleSize(): void
    {
        $options = Options::fromArguments(['--quick']);

        self::assertSame(['page', 'api-get'], $options->list('scenarios'));
        self::assertSame(['2'], $options->list('modules'));
        self::assertSame(40, $options->int('iterations'));
        self::assertSame(10, $options->int('warmup'));
    }

    public function testValuesAreSplitOnCommasAndTrimmed(): void
    {
        $options = Options::fromArguments(['--opcache=off, on ,jit', '--rows=10,1000']);

        self::assertSame(['off', 'on', 'jit'], $options->list('opcache'));
        self::assertSame(['10', '1000'], $options->list('rows'));
    }

    public function testFlagsAndTextOptions(): void
    {
        $options = Options::fromArguments(['--keep', '--json=/tmp/out.json']);

        self::assertTrue($options->has('keep'));
        self::assertFalse($options->has('help'));
        self::assertSame('/tmp/out.json', $options->text('json'));
        self::assertSame('', $options->text('missing'));
    }

    public function testTheScenariosDecideWhichModulesAreNeeded(): void
    {
        $page = Options::fromArguments(['--scenarios=page', '--modules=1']);
        self::assertTrue($page->needsPage());
        self::assertFalse($page->needsApi());
        self::assertSame(['BENCHPAGE'], $page->baseModules());

        $api = Options::fromArguments(['--scenarios=api-get,api-list', '--modules=1']);
        self::assertFalse($api->needsPage());
        self::assertTrue($api->needsApi());
        self::assertSame(['CLIENT'], $api->baseModules());

        $both = Options::fromArguments(['--scenarios=page,api-get']);
        self::assertSame(['BENCHPAGE', 'CLIENT'], $both->baseModules());
    }

    public static function invalidArguments(): array
    {
        return [
            'unknown option' => [['--nope=1'], 'Unknown option --nope'],
            'positional argument' => [['value'], 'Unexpected argument'],
            'unknown scenario' => [['--scenarios=page,checkout'], 'Unknown scenario checkout'],
            'bad opcache' => [['--opcache=fast'], '--opcache accepts off,on,jit'],
            'bad redis' => [['--redis=cluster'], '--redis accepts off,sessions,on'],
            'bad debug' => [['--debug=maybe'], '--debug accepts true,false'],
            'too few modules' => [['--scenarios=page,api-get', '--modules=1'], '--modules needs at least 2'],
        ];
    }

    #[DataProvider('invalidArguments')]
    public function testInvalidArgumentsAreRejectedWithAClearMessage(array $arguments, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        Options::fromArguments($arguments);
    }

    public function testUsageDocumentsEveryOption(): void
    {
        $usage = Options::usage();

        foreach (['--scenarios', '--modules', '--rows', '--opcache', '--debug', '--redis', '--iterations', '--rtt', '--repeat', '--json', '--keep', '--quick'] as $option) {
            self::assertStringContainsString($option, $usage);
        }
    }
}
