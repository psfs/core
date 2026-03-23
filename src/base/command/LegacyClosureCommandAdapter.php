<?php

namespace PSFS\base\command;

use Throwable;

class LegacyClosureCommandAdapter implements CommandHandlerInterface
{
    public function __construct(private string $filePath)
    {
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function handle(CommandContext $context): CommandResult
    {
        if (!is_file($this->filePath)) {
            return CommandResult::failure(sprintf('Legacy command file not found: %s', $this->filePath));
        }

        try {
            $console = $context->getConsole();
            $before = count($console->all());
            include_once $this->filePath;
            $after = count($console->all());

            return CommandResult::success(
                max(0, $after - $before),
                sprintf('Loaded legacy command file: %s', $this->filePath)
            );
        } catch (Throwable $exception) {
            return CommandResult::failure(
                sprintf('Unable to load legacy command file %s: %s', $this->filePath, $exception->getMessage())
            );
        }
    }
}

