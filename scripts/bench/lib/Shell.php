<?php

declare(strict_types=1);

namespace Psfs\Bench;

final class Shell
{
    /**
     * Runs a command and returns its output (stdout and stderr), failing when it exits with an error
     */
    public static function run(string $command): string
    {
        exec($command . ' 2>&1', $output, $code);
        $text = implode("\n", $output);
        if (0 !== $code) {
            throw new \RuntimeException("command failed ($code): $command\n$text");
        }
        return $text;
    }
}
