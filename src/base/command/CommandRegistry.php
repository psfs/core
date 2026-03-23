<?php

namespace PSFS\base\command;

class CommandRegistry
{
    /**
     * @var array<int, CommandHandlerInterface>
     */
    private array $handlers = [];

    public function addHandler(CommandHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;

        return $this;
    }

    public function count(): int
    {
        return count($this->handlers);
    }

    /**
     * @return array<int, CommandHandlerInterface>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * @return array<int, CommandResult>
     */
    public function run(CommandContext $context): array
    {
        $results = [];
        foreach ($this->handlers as $handler) {
            $results[] = $handler->handle($context);
        }

        return $results;
    }
}

