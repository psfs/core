<?php

namespace PSFS\tests\base\command\console;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;
use PSFS\base\command\console\GenerateJWTTokenCommand;
use PSFS\base\command\console\UpgradeProjectCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class ProjectCommandsTest extends TestCase
{
    public function testTheJwtIsSignedWithTheUserAndKeyAndTargetsTheModule(): void
    {
        $tester = new CommandTester(new JwtCommandWithKey('s3cret'));

        $exitCode = $tester->execute(['--user' => 'admin', '--module' => 'SHOP']);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('---------- JWT START ---------', $display);
        preg_match('/JWT START ---------\n(\S+)\n/', $display, $matches);
        $claims = JWT::decode($matches[1], new Key(sha1('admin' . 's3cret'), 'HS256'));
        $this->assertSame('admin', $claims->sub);
        $this->assertSame('SHOP', $claims->aud);
        $this->assertSame('PSFS', $claims->iss);
    }

    public function testTheJwtAudienceDefaultsToAll(): void
    {
        $tester = new CommandTester(new JwtCommandWithKey('k'));

        $tester->execute(['--user' => 'admin', '--module' => '']);

        $this->assertStringContainsString('in module ALL', $tester->getDisplay());
    }

    public function testModuleNamesAreCleanedOfDomainDecorations(): void
    {
        $command = new UpgradeProjectCommand();

        $this->assertSame('SHOP', $command->cleanModuleName('@SHOP'));
        $this->assertSame('ACMEAPI', $command->cleanModuleName('@ACME\\API/'));
    }
}

final class JwtCommandWithKey extends GenerateJWTTokenCommand
{
    public function __construct(private readonly string $key)
    {
        parent::__construct();
    }

    protected function askKey(InputInterface $input, OutputInterface $output): string
    {
        return $this->key;
    }
}
