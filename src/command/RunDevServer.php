<?php

namespace PSFS\Command;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

if (!isset($console)) {
    $console = new Application();
}

function checkCRC(string $folder, array &$cache, ?OutputInterface $output = null): void
{
    // Comprobamos si el directorio existe
    if (!is_dir($folder)) {
        die("El directorio especificado no existe.");
    }

    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterador as $archivo) {
        // Ignora los directorios . y ..
        if ($archivo->isFile()) {
            $crc = hash_file('crc32', $archivo->getPathname());
            if (array_key_exists($archivo->getPathname(), $cache) && $crc !== $cache[$archivo->getPathname()] && $output) {
                $output->writeln("Cambios en fichero " . $archivo->getFilename());
            }
            $cache[$archivo->getPathname()] = $crc;
        }
    }
}

$console
    ->register('psfs:server')
    ->setDefinition([
        new InputArgument('port', InputArgument::OPTIONAL, 'Puerto a usar', 8888),
    ])
    ->setDescription('Ejecutar las migraciones de base de datos')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        // Creates the html path
        $port = $input->getArgument('port');
        if ($port <= 0 || $port > 65535) {
            $output->writeln("Invalid port {$port}");
        }
        $output->writeln("Inicializando PSFS server");
        $files = [];
        checkCRC(CORE_DIR, $files);
        $process = new Process(["php", "-S", "0.0.0.0:{$port}", "-t", realpath(WEB_DIR)]);
        $process->setIdleTimeout(0.0);
        $process->setTimeout(0.0);
        $process->enableOutput();
        $process->start();
        $output->writeln("Servidor PSFS listo en  http://localhost:$port");
        $full_messages = '';
        while ($process->isRunning()) {
            checkCRC(CORE_DIR, $files, $output);
            $return_message = $process->getErrorOutput();
            $err_message = str_replace($full_messages, '', $return_message);
            if (strlen($err_message)) {
                $output->write($err_message);
                $full_messages = $return_message;
            }
        }
    });

