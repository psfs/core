<?php
namespace PSFS\test\base;

use PHPUnit\Framework\TestCase;
use PSFS\base\config\Config;
use PSFS\base\Template;

/**
 * Class TemplateTest
 * @package PSFS\test
 */
class TemplateTest extends TestCase {

    public function testTemplateBasics() {
        $template = Template::getInstance();

        // Check if the template engine is ok
        $engine = $template->getTemplateEngine();
        self::assertNotNull($engine, 'Error at Template creation');
        self::assertInstanceOf('\\Twig\\Environment', $engine);

        // Check if the template loader is ok
        $loader = $template->getLoader();
        self::assertNotNull($loader, 'Error at Template creation');
        self::assertInstanceOf('\\Twig\\Loader\\LoaderInterface', $loader);

        $domains = Template::getDomains(true);
        self::assertNotNull($domains);

        $path = Template::extractPath(__DIR__);
        self::assertNotNull($path);
        self::assertFileExists($path);

        Config::getInstance()->setDebugMode(true);
        $output = $template->dump('index.html.twig');
        self::assertNotNull($output);
        self::assertNotEmpty($output);

        Config::getInstance()->setDebugMode(false);
        $output2 = $template->dump('index.html.twig');
        self::assertNotNull($output2);
        self::assertNotEmpty($output2);
        self::assertNotEquals($output2, $output, 'Production template is the same than development one');
        Config::getInstance()->setDebugMode(true);
    }

    public function testTranslations() {
        $template = Template::getInstance();

        $template->setPublicZone(true);
        self::assertTrue($template->isPublicZone());
        $template->setPublicZone(false);
        self::assertFalse($template->isPublicZone());

        $translations = $template->regenerateTemplates();
        self::assertNotNull($translations);
        self::assertNotEmpty($translations);
    }
}
