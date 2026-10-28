<?php

namespace PSFS\tests\base\command\console;

use PHPUnit\Framework\TestCase;
use PSFS\base\command\console\CreateDocumentRootCommand;
use PSFS\base\command\console\DeployProjectCommand;
use PSFS\base\command\console\GenerateJWTTokenCommand;
use PSFS\base\command\console\QueueDispatchCommand;
use PSFS\base\command\console\QueueRunParallelCommand;
use PSFS\base\command\console\QueueWorkCommand;
use PSFS\base\command\console\UpgradeProjectCommand;
use PSFS\base\queue\ParallelQueueRunner;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\services\GeneratorService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class ProjectLifecycleCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = CACHE_DIR . DIRECTORY_SEPARATOR . 'lifecycle-commands-' . uniqid('', true);
        GeneratorHelper::createDir($this->root);
    }

    protected function tearDown(): void
    {
        GeneratorHelper::deleteDir($this->root);
    }

    public function testCreateRootBuildsTheDocumentRootInTheGivenPath(): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'html';
        GeneratorHelper::createDir($path);
        $tester = new CommandTester(new CreateDocumentRootCommand());

        $exitCode = $tester->execute(['path' => $path]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertFileExists($path . DIRECTORY_SEPARATOR . 'index.php');
        $this->assertStringContainsString('Document root generated at ' . $path, $tester->getDisplay());
    }

    public function testDeployRegeneratesRootCacheAndRoutes(): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'html';
        GeneratorHelper::createDir($path);
        $command = new DeployWithoutSideEffects(true);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['path' => $path]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertFileExists($path . DIRECTORY_SEPARATOR . 'index.php');
        $this->assertSame(1, $command->routesGenerated);
        $this->assertStringContainsString($path, $tester->getDisplay());
        $this->assertStringContainsString('v42', $tester->getDisplay());
    }

    public function testDeployReportsConfigurationFilesThatCouldNotBeCleaned(): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'html';
        GeneratorHelper::createDir($path);
        $cleaned = new CommandTester(new DeployWithoutSideEffects(true));
        $notCleaned = new CommandTester(new DeployWithoutSideEffects(false));

        $cleaned->execute(['path' => $path]);
        $notCleaned->execute(['path' => $path]);

        $this->assertNotSame($cleaned->getDisplay(), $notCleaned->getDisplay());
    }

    public function testUpgradeRewritesTheConfigurationOfEveryModuleAndRedeploys(): void
    {
        $configDir = $this->makeDirectory('config');
        file_put_contents($configDir . '/domains.json', '{}');
        file_put_contents($configDir . '/urls.json', '{}');
        $shop = $this->makeModule('Shop');
        $command = new UpgradeWithoutSideEffects($configDir, [
            '@ROOT' => ['base' => $this->root],
            '@SHOP' => ['base' => $shop],
            '@AUTH' => ['base' => $this->root],
        ]);
        $application = new Application();
        $application->add($command);
        $application->add(new DeployMarkerCommand());
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertFileExists($configDir . '/domains.json.bak');
        $this->assertFileExists($configDir . '/urls.json.bak');
        $this->assertFileDoesNotExist($configDir . '/domains.json');
        $this->assertFileDoesNotExist($shop . '/autoloader.php');
        $this->assertFileDoesNotExist($shop . '/Config/propel.yml');
        $this->assertFileExists($shop . '/Config/config.php.bak');
        $this->assertSame(['SHOP'], $command->generator->modules);
        $this->assertSame(1, $command->routesRegenerated);
        $this->assertStringContainsString('There are 2 modules to update', $tester->getDisplay());
        $this->assertStringContainsString('Updating module SHOP', $tester->getDisplay());
        $this->assertStringContainsString('deploy marker', $tester->getDisplay());
    }

    public function testJwtAsksForTheKeyWhenItIsNotInjected(): void
    {
        $tester = new CommandTester(new GenerateJWTTokenCommand());
        $tester->setInputs(['typed-key']);

        $exitCode = $tester->execute(['--user' => 'admin'], ['interactive' => true]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertStringContainsString('JWT START', $tester->getDisplay());
    }

    public function testDispatchRejectsACodeThatIsNotRegistered(): void
    {
        $tester = new CommandTester(new QueueDispatchCommand());

        $this->expectException(\LogicException::class);

        $tester->execute(['--code' => 'unknown-code-' . uniqid('', true)]);
    }

    public function testWorkConsumesAnEmptyQueueWithTheConfiguredBackend(): void
    {
        $tester = new CommandTester(new QueueWorkCommand());

        $exitCode = $tester->execute(['--queue' => 'empty-' . uniqid('', true), '--stop-when-empty' => 'true']);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
        $this->assertStringContainsString('Processed jobs: 0', $tester->getDisplay());
    }

    public function testRunParallelBuildsTheDefaultRunner(): void
    {
        $command = new RunParallelWithPublicRunner();

        $this->assertInstanceOf(ParallelQueueRunner::class, $command->defaultRunner());
    }

    private function makeDirectory(string $name): string
    {
        $path = $this->root . DIRECTORY_SEPARATOR . $name;
        GeneratorHelper::createDir($path);
        return $path;
    }

    private function makeModule(string $name): string
    {
        $base = $this->makeDirectory($name);
        GeneratorHelper::createDir($base . '/Config');
        file_put_contents($base . '/autoloader.php', '<?php');
        file_put_contents($base . '/Config/domains.json', '{}');
        file_put_contents($base . '/Config/config.php', '<?php');
        file_put_contents($base . '/Config/propel.yml', '');
        return $base;
    }
}

final class DeployWithoutSideEffects extends DeployProjectCommand
{
    public int $routesGenerated = 0;

    public function __construct(private readonly bool $configCleaned)
    {
        parent::__construct();
    }

    protected function cacheState(): array
    {
        return ['version' => 'v42', 'config_files_cleaned' => $this->configCleaned];
    }

    protected function regenerateRoutes(OutputInterface $output): void
    {
        $this->routesGenerated++;
    }
}

final class UpgradeWithoutSideEffects extends UpgradeProjectCommand
{
    public int $routesRegenerated = 0;
    public RecordingGeneratorService $generator;

    /** @param array<string, array<string, mixed>> $moduleList */
    public function __construct(private readonly string $configDir, private readonly array $moduleList)
    {
        parent::__construct();
        $this->generator = new RecordingGeneratorService();
    }

    protected function modules(): array
    {
        return $this->moduleList;
    }

    protected function configDirectory(): string
    {
        return $this->configDir;
    }

    protected function regenerateRoutes(): void
    {
        $this->routesRegenerated++;
    }

    protected function generator(): GeneratorService
    {
        return $this->generator;
    }
}

final class RecordingGeneratorService extends GeneratorService
{
    /** @var list<string> */
    public array $modules = [];

    public function __construct()
    {
    }

    public function generateConfigurationTemplates(string $module, string $modulePath, bool $force = false): void
    {
        $this->modules[] = $module;
    }
}

final class DeployMarkerCommand extends Command
{
    public function __construct()
    {
        parent::__construct(DeployProjectCommand::NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('deploy marker');
        return self::SUCCESS;
    }
}

final class RunParallelWithPublicRunner extends QueueRunParallelCommand
{
    public function defaultRunner(): ParallelQueueRunner
    {
        return $this->runner();
    }
}
