<?php

namespace PSFS\base\command\console;

use PSFS\base\Router;
use PSFS\base\types\helpers\DeployHelper;
use PSFS\base\types\helpers\GeneratorHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * psfs:deploy:project: regenerates the Document Root, the cache version and the routes
 */
class DeployProjectCommand extends Command
{
    public const NAME = 'psfs:deploy:project';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, t('Path where the Document Root will be created'))
            ->setDescription(t('Deployment command for PSFS-based projects'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string)$input->getArgument('path');
        if ('' === $path) {
            $path = WEB_DIR;
        }
        $this->regenerateDocumentRoot($path, $output);
        $this->refreshCache($output);
        $this->regenerateRoutes($output);
        return self::SUCCESS;
    }

    private function regenerateDocumentRoot(string $path, OutputInterface $output): void
    {
        GeneratorHelper::createRoot($path, $output);
        $output->writeln(str_replace('%path', $path, t('Document root regenerated at %path')));
    }

    /**
     * @return array{version:string,config_files_cleaned:bool}
     */
    protected function cacheState(): array
    {
        return DeployHelper::refreshCacheState();
    }

    private function refreshCache(OutputInterface $output): void
    {
        $cacheState = $this->cacheState();
        $output->writeln(str_replace('%version', $cacheState['version'], t('Cache version updated to %version')));
        $output->writeln(
            $cacheState['config_files_cleaned']
                ? t('Configuration files cleaned successfully')
                : t('One or more files in the configuration folder could not be cleaned')
        );
    }

    protected function regenerateRoutes(OutputInterface $output): void
    {
        $router = Router::getInstance();
        $router->hydrateRouting();
        $router->simpatize();
        $output->writeln(t('Project routes generated successfully'));
    }
}
