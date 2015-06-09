<?php
    /**
     * Comando de de creación de estructura de document root
     */
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Helper\QuestionHelper;
    use Symfony\Component\Console\Question\Question;
    use PSFS\controller\Admin;

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
            if(!file_exists($path)) @mkdir($path, 0775, true);
            if(!file_exists($path . DIRECTORY_SEPARATOR . "js")) @mkdir($path . DIRECTORY_SEPARATOR . "js", 0775, true);
            if(!file_exists($path . DIRECTORY_SEPARATOR . "css")) @mkdir($path . DIRECTORY_SEPARATOR . "css", 0775, true);
            if(!file_exists($path . DIRECTORY_SEPARATOR . "img")) @mkdir($path . DIRECTORY_SEPARATOR . "img", 0775, true);
            if(!file_exists($path . DIRECTORY_SEPARATOR . "media")) @mkdir($path . DIRECTORY_SEPARATOR . "media", 0775, true);
            if(!file_exists($path . DIRECTORY_SEPARATOR . "fonts")) @mkdir($path . DIRECTORY_SEPARATOR . "fonts", 0775, true);
            if(!file_exists($path)) throw new \Exception("No tienes privilegios para crear el directorio '{$path}'");
            if(!file_exists(SOURCE_DIR . DIRECTORY_SEPARATOR . 'html.tar.gz')) throw new \Exception("No existe el fichero del DocumentRoot");
            $bin_path = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "bin";
            $tar = new Archive_Tar(realpath(SOURCE_DIR . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "html.tar.gz", true);
            $tar->extract(realpath($path));
            $output->writeln("Document root generado en " . $path);
        })
    ;