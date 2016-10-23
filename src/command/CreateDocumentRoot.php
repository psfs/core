<?php
    /**
     * Comando de de creación de estructura de document root
     */
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    if (!isset($console)) $console = new Application();
    $console
        ->register('psfs:create:root')
        ->setDefinition(array(
            new InputArgument('path', InputArgument::OPTIONAL, 'Path en el que crear el Document Root'),
        ))
        ->setDescription('Comando de creación del Document Root del projecto')
        ->setCode(function(InputInterface $input, OutputInterface $output) {
            $path = $input->getArgument('path');
            if (empty($path)) $path = BASE_DIR.DIRECTORY_SEPARATOR.'html';
            \PSFS\base\config\Config::createDir($path);
            $paths = array("js", "css", "img", "media", "font");
            foreach ($paths as $htmlPath) {
                \PSFS\base\config\Config::createDir($path.DIRECTORY_SEPARATOR.$htmlPath);
            }
            $files = [
                '_' => '_.php',
                'browserconfig' => 'browserconfig.xml',
                'crossdomain' => 'crossdomain.xml',
                'humans' => 'humans.txt',
                'robots' => 'robots.txt',
            ];
            foreach ($files as $templates => $filename) {
                $text = \PSFS\base\Template::getInstance()->dump("generator/html/". $templates . '.html.twig');
                if(false === file_put_contents($path . DIRECTORY_SEPARATOR . $filename, $text)) {
                    $output->writeln('Can\t create the file ' . $filename);
                } else {
                    $output->writeln($filename . ' created successfully');
                }
            }
            $output->writeln("Document root generado en ".$path);
        })
    ;
