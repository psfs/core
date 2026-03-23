<?php

namespace PSFS\tests\base\command;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use PSFS\base\command\CommandContext;
use PSFS\base\command\CommandHandlerInterface;
use PSFS\base\command\CommandRegistry;
use PSFS\base\command\CommandResult;
use Symfony\Component\Console\Application;

class CommandRegistryTest extends TestCase
{
    public function testRunExecutesHandlersInOrder(): void
    {
        $calls = new ArrayObject();
        $registry = new CommandRegistry();
        $registry
            ->addHandler(new TestHandler('first', $calls))
            ->addHandler(new TestHandler('second', $calls));

        $results = $registry->run(new CommandContext(new Application()));

        $this->assertCount(2, $registry->all());
        $this->assertSame(2, $registry->count());
        $this->assertCount(2, $results);
        $this->assertSame('first', $calls[0]);
        $this->assertSame('second', $calls[1]);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertTrue($results[1]->isSuccess());
    }
}

class TestHandler implements CommandHandlerInterface
{
    public function __construct(private string $id, private ArrayObject $calls)
    {
    }

    public function handle(CommandContext $context): CommandResult
    {
        $this->calls->append($this->id);

        return CommandResult::success(1, $this->id);
    }
}

