<?php

namespace PSFS\base\command\console;

use Firebase\JWT\JWT;
use PSFS\base\config\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * psfs:jwt:generate: generates a one hour JWT for a user and module
 */
class GenerateJWTTokenCommand extends Command
{
    public const NAME = 'psfs:jwt:generate';
    private const TTL = 3600;

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'User that generates the token')
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'Module for which the token is generated', 'ALL')
            ->addUsage('psfs:jwt:generate --user=admin')
            ->addUsage('psfs:jwt:generate --user=test --module=TEST')
            ->setDescription('Generate a JWT token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = (string)$input->getOption('user');
        $module = (string)($input->getOption('module') ?: 'ALL');
        $key = $this->askKey($input, $output);
        $output->writeln("Generating JWT for user $user in module $module with key $key");
        $output->writeln('---------- JWT START ---------');
        $output->writeln($this->encode($user, $module, $key));
        $output->writeln('---------- JWT END ---------');
        return self::SUCCESS;
    }

    protected function askKey(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question("Enter the key to generate the JWT:\n", 'string');
        $question->setHidden(true);
        return (string)(new QuestionHelper())->ask($input, $output, $question);
    }

    public function encode(string $user, string $module, string $key): string
    {
        return JWT::encode([
            'iss' => 'PSFS',
            'sub' => $user,
            'aud' => $module,
            'iat' => time(),
            'exp' => time() + self::TTL,
        ], sha1($user . $key), Config::getParam('jwt.alg', 'HS256'));
    }
}
