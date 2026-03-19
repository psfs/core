<?php

namespace PSFS\tests\base\extension;

use PHPUnit\Framework\TestCase;
use PSFS\base\extension\AssetsNode;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Node;

class AssetsNodeTest extends TestCase
{
    public function testCompileGeneratesParserPipelineForScripts(): void
    {
        $scripts = new Node([], ['value' => ['a.js', 'b.js']], 1);
        $node = new AssetsNode(
            'scripts',
            [
                'node' => $scripts,
                'hash' => 'hash-js',
            ],
            1,
            'js'
        );

        $compiler = new Compiler(new Environment(new ArrayLoader()));
        $node->compile($compiler);
        $source = $compiler->getSource();

        $this->assertStringContainsString("new \\PSFS\\base\\extension\\AssetsParser('js')", $source);
        $this->assertStringContainsString("\$parser->setHash('hash-js')", $source);
        $this->assertStringContainsString("\$parser->init('js')", $source);
        $this->assertStringContainsString("\$parser->addFile('a.js')", $source);
        $this->assertStringContainsString("\$parser->addFile('b.js')", $source);
        $this->assertStringContainsString("\$parser->compile()", $source);
        $this->assertStringContainsString("\$parser->printHtml()", $source);
    }

    public function testCompileGeneratesParserPipelineForStyles(): void
    {
        $scripts = new Node([], ['value' => ['a.css']], 1);
        $node = new AssetsNode(
            'styles',
            [
                'node' => $scripts,
                'hash' => 'hash-css',
            ],
            1,
            'css'
        );

        $compiler = new Compiler(new Environment(new ArrayLoader()));
        $node->compile($compiler);
        $source = $compiler->getSource();

        $this->assertStringContainsString("new \\PSFS\\base\\extension\\AssetsParser('css')", $source);
        $this->assertStringContainsString("\$parser->setHash('hash-css')", $source);
        $this->assertStringContainsString("\$parser->init('css')", $source);
        $this->assertStringContainsString("\$parser->addFile('a.css')", $source);
    }
}

