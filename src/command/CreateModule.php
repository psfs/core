<?php
    /**
     * Comando de de creación de estructura de un módulo
     */
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Helper\QuestionHelper;
    use Symfony\Component\Console\Question\Question;
    use PSFS\controller\Admin;

    /**
     * Wrapper para trazar logs en la consola
     * Class CLog
     */
    class CLog{
        private $log;
        private $verbosity;
        public function __construct($log, $verbosity = 0)
        {
            $this->log = $log;
            $this->verbosity = $verbosity;
        }

        public function infoLog($msg)
        {
            if($this->verbosity) $this->log->writeln($msg);
        }
    }

    $console
        ->register('psfs:create:module')
        ->setDefinition(array(
            new InputArgument('module', InputArgument::OPTIONAL, 'Nombre del módulo a crear'),
        ))
        ->setDescription('Comando de creación de módulos psfs')
        ->setCode(function (InputInterface $input, OutputInterface $output) {
            $module = $input->getArgument('module');
            $admin = new Admin();
            $helper = new QuestionHelper();
            $log = new CLog($output, $output->isVerbose());
            try
            {
                //En caso de no tener nombre del módulo lo solicitamos al usuario
                if(empty($module))
                {
                    $question = new Question("Introduce el nombre del módulo a crear:\n");
                    $module = $helper->ask($input, $output, $question);
                }
                //Sólo si tenemos nombre del módulo
                if(!empty($module))
                {
                    $admin->createStructureModule($module, $log);
                }
            }catch(\Exception $e)
            {
                $output->writeln($e->getMessage());
                $output->writeln($e->getTraceAsString());
            }
        })
    ;