<?php

namespace PSFS\services\migration;

class CommandRunner
{
    /**
     * @return array{exit_code:int, output:string}
     */
    public function run(string $command, ?string $cwd = null): array
    {
        $output = [];
        $exitCode = 1;
        $prefixed = null !== $cwd
            ? sprintf('cd %s && %s', escapeshellarg($cwd), $command)
            : $command;
        exec($prefixed . ' 2>&1', $output, $exitCode);

        return [
            'exit_code' => (int)$exitCode,
            'output' => implode(PHP_EOL, $output),
        ];
    }
}
