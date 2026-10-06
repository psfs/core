<?php

namespace PSFS\base\types\traits\Generator;

use PSFS\base\Template;
use PSFS\base\exception\GeneratorException;
use PSFS\base\types\helpers\FileHelper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Builds the framework document root and its starter files.
 *
 * Requires the directory/copy methods from GeneratorFilesystemTrait plus
 * BASE_DIR, SOURCE_DIR, WEB_DIR and PSFS_AS_VENDOR bootstrap constants.
 */
trait GeneratorDocumentRootTrait
{
    public static function getTemplatePath(): string
    {
        $path = SOURCE_DIR . DIRECTORY_SEPARATOR . 'templates';
        $resolvedPath = realpath($path);
        $finalPath = is_string($resolvedPath) && $resolvedPath !== '' ? $resolvedPath : $path;
        return rtrim($finalPath, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $path
     * @param OutputInterface|null $output
     * @param bool $quiet
     * @throws GeneratorException
     */
    public static function createRoot($path = WEB_DIR, $output = null, $quiet = false): void
    {
        $output = $output ?? new ConsoleOutput();
        self::createDocumentRootStructure($path);
        $files = self::getRootFilesToGenerate();
        $verifiable = ['humans', 'robots', 'docker'];
        if (!$quiet) {
            $output->writeln('Start creating html files');
        }
        foreach ($files as $template => $filename) {
            $target = $path . DIRECTORY_SEPARATOR . $filename;
            if (in_array($template, $verifiable, true) && file_exists($target)) {
                self::writeGeneratorOutput($output, $quiet, $filename . ' already exists');
                continue;
            }
            $text = Template::getInstance()->dump(
                'generator/html/' . $template . '.html.twig',
                ['PSFS_AS_VENDOR' => PSFS_AS_VENDOR]
            );
            if (!FileHelper::writeFileAtomic($target, $text)) {
                self::writeGeneratorOutput($output, $quiet, 'Can\t create the file ' . $filename);
                continue;
            }
            self::writeGeneratorOutput($output, $quiet, $filename . ' created successfully');
        }
        self::ensureBaseLocaleExists();
    }

    private static function createDocumentRootStructure(string $path): void
    {
        self::createDir($path);
        foreach (['js', 'css', 'img', 'media', 'font'] as $htmlPath) {
            self::createDir($path . DIRECTORY_SEPARATOR . $htmlPath);
        }
    }

    /** @return array<string, string> */
    private static function getRootFilesToGenerate(): array
    {
        return [
            'index' => 'index.php',
            'browserconfig' => 'browserconfig.xml',
            'crossdomain' => 'crossdomain.xml',
            'humans' => 'humans.txt',
            'robots' => 'robots.txt',
            'docker' => '..' . DIRECTORY_SEPARATOR . 'docker-compose.yml',
        ];
    }

    private static function ensureBaseLocaleExists(): void
    {
        if (!file_exists(BASE_DIR . DIRECTORY_SEPARATOR . 'locale')) {
            self::createDir(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
            self::copyr(
                SOURCE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'locale',
                BASE_DIR . DIRECTORY_SEPARATOR . 'locale'
            );
        }
    }

    private static function writeGeneratorOutput(OutputInterface $output, bool $quiet, string $message): void
    {
        if (!$quiet) {
            $output->writeln($message);
        }
    }
}
