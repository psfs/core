<?php

namespace PSFS\tests\base\type;

use PHPUnit\Framework\TestCase;
use PSFS\base\Template;
use PSFS\base\config\Config;
use PSFS\base\types\Controller;

class ControllerContractProbe extends Controller
{
    public function init()
    {
        // Prevent Singleton bootstrap side-effects in unit tests.
    }

    public function exposeSetDomain(string $domain): self
    {
        return $this->setDomain($domain);
    }

    public function exposeSetTemplatePath(string $path): self
    {
        return $this->setTemplatePath($path);
    }

    protected function getMenu()
    {
        return ['dashboard'];
    }
}

class ControllerContractTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        $this->configBackup = Config::getInstance()->dumpConfig();
    }

    protected function tearDown(): void
    {
        Config::save($this->configBackup, []);
        Config::getInstance()->loadConfigData(true);
    }

    public function testRenderUsesDefaultDomainAndAddsMenu(): void
    {
        Config::save(array_merge($this->configBackup, ['profiling.enable' => false]), []);
        Config::getInstance()->loadConfigData(true);

        $tpl = $this->createMock(Template::class);
        $tpl->expects($this->once())
            ->method('render')
            ->with(
                '@TEST/view.html.twig',
                $this->callback(function (array $vars): bool {
                    $this->assertSame(['dashboard'], $vars['__menu__']);
                    $this->assertSame('ok', $vars['state']);
                    $this->assertArrayNotHasKey('__profiling__', $vars);
                    return true;
                }),
                []
            )
            ->willReturn('rendered');

        $probe = $this->newProbe($tpl);
        $probe->exposeSetDomain('TEST');

        $this->assertSame('rendered', $probe->render('view.html.twig', ['state' => 'ok']));
    }

    public function testDumpUsesExplicitDomain(): void
    {
        $tpl = $this->createMock(Template::class);
        $tpl->expects($this->once())
            ->method('dump')
            ->with(
                '@ALT/report.twig',
                $this->callback(function (array $vars): bool {
                    $this->assertSame(['dashboard'], $vars['__menu__']);
                    return true;
                })
            )
            ->willReturn('dumped');

        $probe = $this->newProbe($tpl);
        $probe->exposeSetDomain('ROOT');

        $this->assertSame('dumped', $probe->dump('report.twig', [], '@ALT/'));
    }

    public function testRenderAddsProfilingBlockWhenEnabled(): void
    {
        Config::save(array_merge($this->configBackup, ['profiling.enable' => true]), []);
        Config::getInstance()->loadConfigData(true);

        $tpl = $this->createMock(Template::class);
        $tpl->expects($this->once())
            ->method('render')
            ->with(
                '@ROOT/dashboard.twig',
                $this->callback(function (array $vars): bool {
                    $this->assertArrayHasKey('__profiling__', $vars);
                    return true;
                }),
                []
            )
            ->willReturn('ok');

        $probe = $this->newProbe($tpl);
        $probe->exposeSetDomain('ROOT');
        $this->assertSame('ok', $probe->render('dashboard.twig'));
    }

    public function testSetTemplatePathDelegatesToTemplateService(): void
    {
        $tpl = $this->createMock(Template::class);
        $tpl->expects($this->once())
            ->method('addPath')
            ->with('/tmp/templates', 'ROOT');

        $probe = $this->newProbe($tpl);
        $probe->exposeSetTemplatePath('/tmp/templates');
    }

    public function testGetDomainUsesTemplatePrefixFormat(): void
    {
        $probe = $this->newProbe($this->createMock(Template::class));
        $probe->exposeSetDomain('MODULE');

        $this->assertSame('@MODULE/', $probe->getDomain());
    }

    private function newProbe(Template $tpl): ControllerContractProbe
    {
        $reflection = new \ReflectionClass(ControllerContractProbe::class);
        /** @var ControllerContractProbe $instance */
        $instance = $reflection->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(Controller::class, 'tpl');
        $property->setAccessible(true);
        $property->setValue($instance, $tpl);
        return $instance;
    }
}
