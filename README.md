PSFS
====
[![Build Status](https://travis-ci.org/c15k0/psfs.svg?branch=master)](https://travis-ci.org/c15k0/psfs)
[![Latest Stable Version](https://poser.pugx.org/c15k0/psfs/v/stable)](https://packagist.org/packages/c15k0/psfs) 
[![Total Downloads](https://poser.pugx.org/c15k0/psfs/downloads)](https://packagist.org/packages/c15k0/psfs) 
[![Latest Unstable Version](https://poser.pugx.org/c15k0/psfs/v/unstable)](https://packagist.org/packages/c15k0/psfs) [![License](https://poser.pugx.org/c15k0/psfs/license)](https://packagist.org/packages/c15k0/psfs)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/c15k0/psfs/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/c15k0/psfs/?branch=master)

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/88c256d9-4e78-4bc3-b25f-e4ef023ac403/big.png)](https://insight.sensiolabs.com/projects/88c256d9-4e78-4bc3-b25f-e4ef023ac403)

Framework Php Simple Fast & Secure

Componentes necesarios para su ejecución:

    * "propel/propel": "2.0.*@dev",
    * "symfony/console": "2.6.*@dev",
    * "symfony/finder": "2.3.*@dev",
    * "monolog/monolog": "1.10.*@dev",
    * "twig/twig": "1.*@dev"
    * "twig/extensions": "1.1.*@dev",
    * "monolog/monolog": "dev-master",
    * "pear/archive_tar": "master"

Para instalar usar composer:

    composer require c15k0/psfs
    ./vendor/bin/psfs psfs:create:root

RoadMap:

    * Gestión de sesiones
        - Motores de sesión(mongodb, filesystem, mysql...)
    * Adaptación backend a angular
        - Inclusión angular en gestión de menús
        - Angular Materials
    * Mejora sistema de cache(html, php, json)
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
    * Documentación framework
        - Autodocumentación servicios
        - Documentación general

