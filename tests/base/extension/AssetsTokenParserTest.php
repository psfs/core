<?php

namespace PSFS\tests\base\extension;

use PHPUnit\Framework\TestCase;
use PSFS\base\extension\AssetsTokenParser;

class AssetsTokenParserTestProxy extends AssetsTokenParser
{
    public function exposeBuildHash(string $path, int $line): string
    {
        return $this->buildHash($path, $line);
    }
}

class AssetsTokenParserTest extends TestCase
{
    public function testHashIsStableForSamePathLineAndType(): void
    {
        $parser = new AssetsTokenParserTestProxy('js');
        $hashA = $parser->exposeBuildHash('/tmp/template.twig', 10);
        $hashB = $parser->exposeBuildHash('/tmp/template.twig', 10);
        $this->assertSame($hashA, $hashB);
    }

    public function testHashChangesForDifferentLinesInSameTemplate(): void
    {
        $parser = new AssetsTokenParserTestProxy('js');
        $hashA = $parser->exposeBuildHash('/tmp/template.twig', 10);
        $hashB = $parser->exposeBuildHash('/tmp/template.twig', 20);
        $this->assertNotSame($hashA, $hashB);
    }

    public function testHashChangesForDifferentTypes(): void
    {
        $jsParser = new AssetsTokenParserTestProxy('js');
        $cssParser = new AssetsTokenParserTestProxy('css');
        $hashJs = $jsParser->exposeBuildHash('/tmp/template.twig', 10);
        $hashCss = $cssParser->exposeBuildHash('/tmp/template.twig', 10);
        $this->assertNotSame($hashJs, $hashCss);
    }
}

