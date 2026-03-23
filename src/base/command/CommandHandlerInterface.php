<?php

namespace PSFS\base\command;

interface CommandHandlerInterface
{
    public function handle(CommandContext $context): CommandResult;
}

