<?php
namespace PSFS\test;

use PSFS\base\Template;

/**
 * Class TemplateTest
 * @package PSFS\test
 */
class TemplateTest extends \PHPUnit_Framework_TestCase {

    public function testTemplateBasics() {
        $template = Template::getInstance();

        // Check if the template engine is ok
        $engine = $template->getTemplateEngine();
        $this->assertNotNull($engine, 'Error at Template creation');
        $this->assertInstanceOf('\\Twig_Environment', $engine);

        // Check if the template loader is ok
        $loader = $template->getLoader();
        $this->assertNotNull($loader, 'Error at Template creation');
        $this->assertInstanceOf('\\Twig_LoaderInterface', $loader);

        $domains = Template::getDomains(true);
        $this->assertNotNull($domains);

        $path = Template::extractPath(__DIR__);
        $this->assertNotNull($path);
        $this->assertFileExists($path);

        $output = $template->dump('index.html.twig');
        $this->assertNotNull($output);
        $this->assertNotEmpty($output);

    }

    public function testTranslations() {
        $template = Template::getInstance();

        $template->setPublicZone(true);
        $this->assertTrue($template->isPublicZone());
        $template->setPublicZone(false);
        $this->assertFalse($template->isPublicZone());

        $translations = $template->regenerateTemplates();
        $this->assertNotNull($translations);
        $this->assertNotEmpty($translations);
    }
}