<?php
    /**
     * Comando de de creación de estructura de document root
     */
    use Symfony\Component\Console\Helper\QuestionHelper;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Question\Question;

    if(!isset($console)) $console = new Application();
    $console
        ->register('psfs:create:root')
        ->setDefinition(array(
            new InputArgument('path', InputArgument::OPTIONAL, 'Path en el que crear el Document Root'),
        ))
        ->setDescription('Comando de creación del Document Root del projecto')
        ->setCode(function (InputInterface $input, OutputInterface $output) {
            $path = $input->getArgument('path');
            if(empty($path)) $path = BASE_DIR . DIRECTORY_SEPARATOR . 'html';
            \PSFS\base\config\Config::createDir($path);
            $paths = array("js", "css", "img", "media", "fonts");
            foreach($paths as $htmlPath) {
                \PSFS\base\config\Config::createDir($path . DIRECTORY_SEPARATOR . $htmlPath);
            }
            if(!file_exists(SOURCE_DIR . DIRECTORY_SEPARATOR . 'html.tar.gz')) throw new \PSFS\base\exception\ConfigException("No existe el fichero del DocumentRoot");
            $tar = new Archive_Tar(realpath(SOURCE_DIR . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "html.tar.gz", true);
            $tar->extract(realpath($path));
            $output->writeln("Document root generado en " . $path);
        })
    ;