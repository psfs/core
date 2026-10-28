<?php

namespace PSFS\base\command\console;

use PSFS\base\types\helpers\GeneratorHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * psfs:create:root: creates the project Document Root
 */
class CreateDocumentRootCommand extends Command
{
    public const NAME = 'psfs:create:root';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Path where the Document Root will be created')
            ->setDescription('Command to create the project Document Root');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string)$input->getArgument('path');
        if ('' === $path) {
            $path = WEB_DIR;
        }
        GeneratorHelper::createRoot($path, $output);
        $output->writeln('Document root generated at ' . $path);
        return self::SUCCESS;
    }
}
