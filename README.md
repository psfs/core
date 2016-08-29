PSFS
====
[![Build Status](https://travis-ci.org/psfs/core.svg?branch=master)](https://travis-ci.org/c15k0/psfs)
[![Latest Stable Version](https://poser.pugx.org/psfs/core/v/stable)](https://packagist.org/packages/c15k0/psfs) 
[![Total Downloads](https://poser.pugx.org/psfs/core/downloads)](https://packagist.org/packages/c15k0/psfs) 
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/psfs/core/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/c15k0/psfs/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/psfs/core/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/c15k0/psfs/?branch=master)

Framework Php Simple Fast & Secure

Needed components to execute PSFS:

    * "propel/propel": "^2.0",
    * "symfony/console": "^3.0",
    * "symfony/finder": "2.3.x-dev",
    * "twig/twig": "1.x-dev",
    * "twig/extensions": "dev-master",
    * "monolog/monolog": "1.x-dev",
    * "pear/archive_tar": "dev-master",
    * "tedivm/jshrink": "~1.0",
    * "natxet/CssMin": "^3.0@dev"

How to install using composer:

   Install composer via: [GetComposer](https://getcomposer.org/download/)
    php composer.phar require c15k0/psfs
    ./vendor/bin/psfs psfs:create:root

RoadMap:

    * Session management
        - Session engines(mongodb, filesystem, mysql...)
    * Migrate admin site to angular
        - Use angular for menus
    * Improve cache(html, php, json)
        - Serialize business logics
        - Html buffer
    * Dynamic site to manage modules
        - Admin site to manage logic models
        - Admin site to manage workflows
        - Admin site to manage web pages
    * Framework documentation
        - Self documentation for apis(swagger and postman outputs)
        - PhpDoc for all files
    * Code coverage
        - 100% tests coverage

