<?php

namespace PSFS\tests\base\type\helper;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class FrameworkAnnotationGuardTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private const FORBIDDEN_TAGS = [
        'Injectable',
        'route',
        'Route',
        'ROUTE',
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'PATCH',
        'HEAD',
        'label',
        'icon',
        'visible',
        'cache',
        'CACHE',
        'header',
        'default',
        'required',
        'values',
        'payload',
        'PAYLOAD',
        'domain',
        'api',
        'action',
    ];

    public function testSrcContainsNoFrameworkDocblockAnnotations(): void
    {
        $sourcePath = realpath(__DIR__ . '/../../../../src');
        $this->assertNotFalse($sourcePath);

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcePath));
        $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i');
        $hits = [];
        foreach ($phpFiles as $fileInfo) {
            $path = (string)$fileInfo->getPathname();
            $lines = @file($path);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $lineNumber => $line) {
                if (!preg_match('/^\s*\*\s*@([A-Za-z_][A-Za-z0-9_]*)\b/', $line, $matches)) {
                    continue;
                }
                if (!in_array($matches[1], self::FORBIDDEN_TAGS, true)) {
                    continue;
                }
                $hits[] = sprintf('%s:%d -> %s', $path, $lineNumber + 1, trim($line));
            }
        }

        $this->assertSame(
            [],
            $hits,
            "Framework annotations still present in src:\n" . implode("\n", $hits)
        );
    }
}
