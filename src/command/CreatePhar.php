<?php
namespace PSFS\Command;

use FilesystemIterator;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:create:phar')
    ->setDefinition(array(
        new InputArgument('project', InputArgument::OPTIONAL, t('Nombre del proyecto')),
    ))
    ->setDescription(t('Comando que compila un proyecto PSFS en un único fichero ejecutable Phar'))
    ->setCode(function (InputInterface $input, OutputInterface $output) {

        $project = $input->getArgument('project');
        if (empty($project)) {
            $project = 'psfs';
        }
        ini_set('memory_limit', -1);
        if(file_exists($project . '.phar')) {
            @unlink($project . '.phar');
        }
        $phar = new Phar( $project . '.phar');
        $phar = $phar->convertToExecutable(Phar::PHAR);
        $phar->buildFromIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(SOURCE_DIR, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            )
            , '.'
        );
        $phar->addFromString('src/environment.php', '<?php define("SOURCE_DIR", "' . CORE_DIR . '"); ?>');
        $phar->buildFromIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(LOCALE_DIR, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            )
            , '.'
        );
        $phar->buildFromIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(VENDOR_DIR, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            )
            , '.'
        );
        $phar->setDefaultStub('vendor/autoload.php', 'autoload.php');
    });

