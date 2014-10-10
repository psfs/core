PSFS
====
[![Build Status](https://travis-ci.org/c15k0/psfs.svg?branch=master)](https://travis-ci.org/c15k0/psfs)

Framework Php Simple Fast & Secure

Componentes necesarios para su ejecución:

    * "propel/propel": "2.0.*@dev",
    * "symfony/console": "2.6.*@dev",
    * "symfony/finder": "2.3.*@dev",
    * "monolog/monolog": "1.10.*@dev",
    * "twig/twig": "1.*@dev"


Para instalar usar composer:

    composer require c15k0/psfs
    ./vendor/bin/psfs psfs:create:root

RoadMap:

    * Gestión de sesiones
        - Control de datos de sesión
        - Uso de sesión en plantillas twig
        - Generación de flashes
        - Motores de sesión
    * Adaptación backend a angular
        - Inclusión angular en gestión de menús
        - AngularStrap
    * Mejora sistema de cache(html, php, json)
        - Gestor de ficheros de cache
        - Serializado de lógicas de negocio
        - Buffer de html
    * Gestión central de sincronizado(pSync)
        - Control de versiones controlada desde admin
        - Gestión composer desde admin
        - Control de despliegues remotos
    * Motor dinámico de creación de módulos
        - Entorno visual de creación de modelos
        - Entorno visual de control de flujos
        - Entorno visual de generación de websites

