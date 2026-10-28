<?php

namespace PSFS\base\command\console;

use PSFS\base\Router;
use PSFS\services\GeneratorService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * psfs:update:project: rewrites the project and module configuration and redeploys
 */
class UpgradeProjectCommand extends Command
{
    public const NAME = 'psfs:update:project';
    private const SKIPPED_MODULES = ['ROOT', 'NOSQL', 'AUTH'];

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription(t('Update PSFS project configuration'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->backupProjectConfig();
        $modules = $this->modules();
        $output->writeln('There are ' . (count($modules) - 1) . ' modules to update');
        foreach ($modules as $module => $config) {
            $name = $this->cleanModuleName((string)$module);
            if (!in_array($name, self::SKIPPED_MODULES, true)) {
                $output->write("\t- Updating module {$name}");
                $this->upgradeModule($name, (string)$config['base']);
                $output->writeln("\tDONE!");
            }
        }
        $this->regenerateRoutes();
        $output->writeln(t('Routes updated successfully'));
        return $this->getApplication()->doRun(new ArrayInput(['command' => DeployProjectCommand::NAME]), $output);
    }

    public function cleanModuleName(string $module): string
    {
        return str_replace(['@', '\\', '/'], '', $module);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function modules(): array
    {
        return Router::getInstance()->getDomains();
    }

    protected function configDirectory(): string
    {
        return CONFIG_DIR;
    }

    protected function regenerateRoutes(): void
    {
        $router = Router::getInstance();
        $router->hydrateRouting();
        $router->simpatize();
    }

    protected function generator(): GeneratorService
    {
        return GeneratorService::getInstance();
    }

    private function backupProjectConfig(): void
    {
        foreach (['domains.json', 'urls.json'] as $file) {
            $path = $this->configDirectory() . DIRECTORY_SEPARATOR . $file;
            if (file_exists($path)) {
                rename($path, $path . '.bak');
            }
        }
    }

    private function upgradeModule(string $name, string $base): void
    {
        $configPath = $base . DIRECTORY_SEPARATOR . 'Config';
        if (file_exists($base . DIRECTORY_SEPARATOR . 'autoloader.php')) {
            unlink($base . DIRECTORY_SEPARATOR . 'autoloader.php');
        }
        if (file_exists($configPath . DIRECTORY_SEPARATOR . 'domains.json')) {
            rename($configPath . DIRECTORY_SEPARATOR . 'config.php', $configPath . DIRECTORY_SEPARATOR . 'config.php.bak');
        }
        if (file_exists($configPath . DIRECTORY_SEPARATOR . 'propel.yml')) {
            unlink($configPath . DIRECTORY_SEPARATOR . 'propel.yml');
        }
        $this->generator()->generateConfigurationTemplates($name, $base);
    }
}
