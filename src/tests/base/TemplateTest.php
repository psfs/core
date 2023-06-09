<?php
namespace PSFS\tests\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\Template;

/**
 * Class TemplateTest
 * @package PSFS\tests
 */
class TemplateTest extends TestCase {

    /**
     * @return void
     */
    public function testTemplateBasics() {
        $template = Template::getInstance();

        // Check if the template engine is ok
        $engine = $template->getTemplateEngine();
        $this->assertNotNull($engine, 'Error at Template creation');
        $this->assertInstanceOf('\\Twig\\Environment', $engine);

        // Check if the template loader is ok
        $loader = $template->getLoader();
        $this->assertNotNull($loader, 'Error at Template creation');
        $this->assertInstanceOf('\\Twig\\Loader\\LoaderInterface', $loader);

        $domains = Template::getDomains(true);
        $this->assertNotNull($domains);

        $path = Template::extractPath(__DIR__);
        $this->assertNotNull($path);
        $this->assertFileExists($path);

        Config::getInstance()->setDebugMode(true);
        $output = $template->dump('index.html.twig');
        $this->assertNotNull($output);
        $this->assertNotEmpty($output);

        Config::getInstance()->setDebugMode(false);
        $output2 = $template->dump('index.html.twig');
        $this->assertNotNull($output2);
        $this->assertNotEmpty($output2);
        $this->assertNotEquals($output2, $output, 'Production template is the same than development one');
        Config::getInstance()->setDebugMode(true);
    }

    /**
     * @return void
     */
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
