<?php

namespace PSFS\base\types\traits\Helper;

use PSFS\base\exception\GeneratorException;
use PSFS\base\types\helpers\GeneratorHelper;

trait I18nDiscoveryTrait
{
    /**
     * @param string $path
     * @param string $locale
     * @return array
     * @throws GeneratorException
     */
    public static function findTranslations(string $path, string $locale): array
    {
        if (!self::isValidLocale($locale)) {
            throw new GeneratorException(t('Invalid locale format'));
        }
        $localePath = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $localePath .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;
        return iterator_to_array(self::yieldTranslations($path, $localePath), false);
    }

    /**
     * @param string $localePath
     * @return string
     */
    public static function compileTranslations(string $localePath): string
    {
        $poPath = escapeshellarg($localePath . 'translations.po');
        $moPath = escapeshellarg($localePath . 'translations.mo');
        $command = "export PATH=\$PATH:/opt/local/bin:/bin:/sbin; msgfmt {$poPath} -o {$moPath}";
        return self::executeShellCommand($command);
    }

    /**
     * @param string $path
     * @param string $localePath
     * @return \Generator
     * @throws GeneratorException
     */
    private static function yieldTranslations(string $path, string $localePath): \Generator
    {
        if (!file_exists($path)) {
            return;
        }
        $directory = dir($path);
        if (false === $directory) {
            return;
        }
        try {
            while (false !== ($fileName = $directory->read())) {
                self::ensureTranslationsPoFile($localePath);
                $inspectPath = realpath($path . DIRECTORY_SEPARATOR . $fileName);
                if (false !== $inspectPath && is_dir($path . DIRECTORY_SEPARATOR . $fileName) && preg_match(
                        '/^\./',
                        $fileName
                    ) == 0) {
                    $phpFiles = glob($inspectPath . DIRECTORY_SEPARATOR . '*.php') ?: [];
                    $outputPo = escapeshellarg($localePath . 'translations.po');
                    $commandOutput = '';
                    $cmdPhp = t('No PHP files found in directory');
                    if (!empty($phpFiles)) {
                        $escapedFiles = array_map('escapeshellarg', $phpFiles);
                        $cmdPhp = "export PATH=\$PATH:/opt/local/bin:/bin:/sbin; xgettext " .
                            implode(' ', $escapedFiles) .
                            " --from-code=UTF-8 -j -L PHP --debug --force-po -o {$outputPo}";
                        $commandOutput = self::executeShellCommand($cmdPhp);
                    }
                    $res = t('Reviewing directory: ') . $inspectPath;
                    $res .= t('Executed command: ') . $cmdPhp;
                    $res .= $commandOutput;
                    usleep(10);
                    yield $res;
                    yield from self::yieldTranslations($inspectPath, $localePath);
                }
            }
        } finally {
            $directory->close();
        }
    }

    protected static function executeShellCommand(string $command): string
    {
        return shell_exec($command) ?: '';
    }

    protected static function ensureTranslationsPoFile(string $localePath): void
    {
        GeneratorHelper::createDir($localePath);
        if (!file_exists($localePath . 'translations.po')) {
            file_put_contents($localePath . 'translations.po', '');
        }
    }
}
