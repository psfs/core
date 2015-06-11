<?php

namespace PSFS\test;

use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\Dispatcher;

class PSFSTest extends \PHPUnit_Framework_TestCase{

    /**
     * Test básico para el funcionamiento del Dispatcher
     */
    public function testDispatcher()
    {
        /** @var \PSFS\Dispatcher $dispatcher */
        $dispatcher = Dispatcher::getInstance();

        // Es instancia de Dispatcher
        $this->assertTrue($dispatcher instanceof Dispatcher);

        // Ha generado un timestamp
        $this->assertTrue($dispatcher->getTs() > 0);
    }

    /**
     * Text básico para el funcionamiento del Config
     */
    public function testConfig()
    {
        $config = Config::getInstance();

        // Es instancia de Config
        $this->assertTrue($config instanceof Config);

        // Comprobamos que devuelva correctamente si está configurada o no la plataforma
        $this->assertTrue(is_bool($config->isConfigured()));

        // Comprobamos devuelva correctamente si estamos en modo debug
        $this->assertTrue(is_bool($config->getDebugMode()));

        // Comprobamos la extracción de variables de configuración
        $this->assertEmpty($config->get(uniqid()));
    }

    /**
     * Test básico para el funcionamiento del Router
     */
    public function testRouter()
    {
        $router = Router::getInstance();

        // Es instancia de Router
        $this->assertTrue($router instanceof Router);

        // Revisamos que exista el fichero de rutas
        $this->assertFileExists(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json");

        // Revisamos que tengamos rutas de administración y como mínimo la de admin
        $adminRoutes = $router->getAdminRoutes();
        $this->assertNotEmpty($adminRoutes);
        $this->assertArrayHasKey("superadmin", $adminRoutes);
    }

    /**
     * Test básico para el funcionamiento de Security
     */
    public function testSecurity()
    {
        $security = Security::getInstance();

        // Es instancia de Security
        $this->assertTrue($security instanceof Security);
    }

    /**
     * Test básico para el funcionamiento de Request
     */
    public function testRequest()
    {
        $request = Request::getInstance();

        // Es instancia de Request
        $this->assertTrue($request instanceof Request);

        // Verificamos que los comprobadores de cabeceras y uploads funcionan
        $this->assertTrue(is_bool($request->hasHeader("session")));
        $this->assertTrue(is_bool($request->hasUpload()));
        $this->assertTrue(is_bool($request->hasCookies()));
        $this->assertTrue(is_bool($request->isAjax()));

        // Verificamos que se setee el timestamp de arranque
        $this->assertNotNull($request->getTs());
    }

}
