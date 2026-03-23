<?php

namespace PSFS\tests\services\migration;

use PHPUnit\Framework\TestCase;
use PSFS\services\migration\CommandRunner;

class CommandRunnerTest extends TestCase
{
    public function testRunCapturesSuccessOutput(): void
    {
        $runner = new CommandRunner();

        $result = $runner->run('printf "ok"');

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame('ok', $result['output']);
    }

    public function testRunUsesWorkingDirectoryWhenProvided(): void
    {
        $runner = new CommandRunner();
        $cwd = sys_get_temp_dir();

        $result = $runner->run('pwd', $cwd);

        $this->assertSame(0, $result['exit_code']);
        $this->assertSame($cwd, trim($result['output']));
    }
}
