<?php

namespace PSFS\Command;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use Firebase\JWT\JWT;
use PSFS\base\config\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

if (!isset($console)) {
    $console = new Application();
}
$console
    ->register('psfs:jwt:generate')
    ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'User that generates the token')
    ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Module for which the token is generated', 'ALL')
    ->addUsage('psfs:jwt:generate --user=admin')
    ->addUsage('psfs:jwt:generate --user=test --module=TEST')
    ->setDescription('Generate a JWT token')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $user = $input->getOption('user');
        $module = $input->getOption('module');
        if (empty($module)) {
            $module = 'ALL';
        }
        $helper = new QuestionHelper();
        $password = new Question("Enter the key to generate the JWT:\n", "string");
        $password->setHidden(true);
        $key = $helper->ask($input, $output, $password);
        $output->writeln("Generating JWT for user $user in module $module with key $key");
        $jwt = JWT::encode([
            'iss' => 'PSFS',
            'sub' => $user,
            'aud' => $module,
            'iat' => time(),
            'exp' => time() + 3600,
        ], sha1($user . $key), Config::getParam('jwt.alg', 'HS256'));
        $output->writeln("---------- JWT START ---------");
        $output->writeln($jwt);
        $output->writeln("---------- JWT END ---------");
    });
