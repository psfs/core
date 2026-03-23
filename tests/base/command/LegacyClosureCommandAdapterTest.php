<?php

namespace PSFS\tests\base\command;

use PHPUnit\Framework\TestCase;
use PSFS\base\command\CommandContext;
use PSFS\base\command\LegacyClosureCommandAdapter;
use Symfony\Component\Console\Application;

class LegacyClosureCommandAdapterTest extends TestCase
{
    public function testHandleLoadsLegacyFileAndRegistersCommand(): void
    {
        $legacyFile = $this->createLegacyCommandFile();
        $application = new Application();
        $adapter = new LegacyClosureCommandAdapter($legacyFile);

        $result = $adapter->handle(new CommandContext($application));

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1, $result->getRegisteredCommands());
        $this->assertTrue($application->has('legacy:test'));
        unlink($legacyFile);
    }

    public function testHandleUsesIncludeOnceAndDoesNotDuplicateCommands(): void
    {
        $legacyFile = $this->createLegacyCommandFile();
        $application = new Application();
        $adapter = new LegacyClosureCommandAdapter($legacyFile);
        $context = new CommandContext($application);

        $first = $adapter->handle($context);
        $second = $adapter->handle($context);

        $this->assertTrue($first->isSuccess());
        $this->assertTrue($second->isSuccess());
        $this->assertSame(1, $first->getRegisteredCommands());
        $this->assertSame(0, $second->getRegisteredCommands());
        unlink($legacyFile);
    }

    public function testHandleFailsWhenFileDoesNotExist(): void
    {
        $adapter = new LegacyClosureCommandAdapter('/tmp/psfs_missing_' . uniqid('', true) . '.php');

        $result = $adapter->handle(new CommandContext(new Application()));

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Legacy command file not found', (string)$result->getMessage());
    }

    private function createLegacyCommandFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'psfs_legacy_cmd_');
        if (false === $file) {
            $this->fail('Unable to create temp command file');
        }

        $content = <<<'PHP'
<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    throw new RuntimeException('Legacy command file requires $console variable');
}

$console
    ->register('legacy:test')
    ->setDescription('Legacy command for tests')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        return 0;
    });
PHP;
        file_put_contents($file, $content);

        return $file;
    }
}

