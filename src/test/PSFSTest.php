<?php

namespace PSFS\test;

use PSFS\base\config\Config;
use PSFS\base\Router;
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

        // Comprobamos la extracción de variables de configuración
        $this->assertEmpty($config->get(uniqid()));
        $this->assertNotEmpty($config->get("default_language"));
    }

    /**
     * Test básico para el funcionamiento del Router
     */
    public function testRouter()
    {
        $router = Router::getInstance();

        // Es instancia de Router
        $this->assertTrue($router instanceof $router);
    }
}