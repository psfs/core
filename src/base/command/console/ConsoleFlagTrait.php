<?php

namespace PSFS\base\command\console;

/**
 * Reads the 1/true/yes style flags used by the console options
 */
trait ConsoleFlagTrait
{
    protected function isFlagOn(mixed $value): bool
    {
        return in_array((string)$value, ['1', 'true', 'yes'], true);
    }
}
