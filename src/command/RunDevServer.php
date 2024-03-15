<?php

namespace PSFS\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (!isset($console)) {
    $console = new Application();
}

function checkCRC(string $folder, array &$cache, ?OutputInterface $output = null): void
{
    // Comprobamos si el directorio existe
    if (!is_dir($folder)) {
        die("El directorio especificado no existe.");
    }

    // Abre el directorio
    if ($manager = opendir($folder)) {
        // Recorre los archivos y directorios del directorio
        while (false !== ($file = readdir($manager))) {
            // Excluye los directorios especiales . y ..
            if ($file != "." && $file != "..") {
                // Construye la ruta completa del archivo o subdirectorio
                $path = $folder . DIRECTORY_SEPARATOR . $file;
                // Si es un archivo, calcula su CRC
                if (is_file($path)) {
                    $crc = hash_file('crc32', $path);
                    if (array_key_exists($path, $cache) && $crc !== $cache[$path] && $output) {
                        $output->writeln("Cambios en fichero $file");
                    }
                    $cache[$path] = $crc;
                } elseif (is_dir($path)) {
                    // Si es un directorio, llama a la función recursivamente
                    checkCRC($path, $cache);
                }
            }
        }
        // Cierra el gestor del directorio
        closedir($manager);
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
        $output->writeln("Inicializando PSFS server");
        $files = [];
        checkCRC(CORE_DIR, $files);
        $server = proc_open("php -S 0.0.0.0:$port -t " . WEB_DIR, [
            0 => ["pipe", "r"],  // stdin es una tubería que el proceso hijo leerá
            1 => ["pipe", "w"],  // stdout es una tubería que el proceso padre escribirá
            2 => ["pipe", "w"],   // stderr es una tubería que el proceso padre escribirá
        ], $pipes);
        sleep(1);
        $output->writeln("Servidor PSFS listo en  http://localhost:$port");
        while ($server) {
            checkCRC(CORE_DIR, $files, $output);
            $return_message = fgets($pipes[1]);
            if (strlen($return_message)) {
                echo $return_message . "\n";
                ob_flush();
                flush();
            }
        }
    });

