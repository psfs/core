# PSFS
====
[![Build Status](https://travis-ci.org/psfs/core.svg?branch=master)](https://travis-ci.org/psfs/core)
[![Latest Stable Version](https://poser.pugx.org/psfs/core/v/stable)](https://packagist.org/packages/psfs/core) 
[![Total Downloads](https://poser.pugx.org/psfs/core/downloads)](https://packagist.org/packages/psfs/core) 
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/psfs/core/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/psfs/core/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)

##Framework Php Simple Fast & Secure

Needed components to execute PSFS:

    * "propel/propel": "^2.0",
    * "symfony/console": "3.0.x-dev",
    * "symfony/finder": "3.0.x-dev",
    * "twig/twig": "1.x-dev",
    * "twig/extensions": "dev-master",
    * "monolog/monolog": "1.x-dev",
    * "tedivm/jshrink": "~1.0",
    * "natxet/CssMin": "^3.0@dev"

How to install using composer:

Install composer via: [GetComposer](https://getcomposer.org/download/)
   
```
php composer.phar require psfs/core
./vendor/bin/psfs psfs:create:root
```

RoadMap:

    * Session management
        - Session engines(mongodb, filesystem, mysql...)
        - Security improvements
    * Improve cache(html, php, json)
        - Serialize business logics
        - Html buffer
    * Dynamic site to manage modules
        - Admin site to manage logic models
        - Admin site to manage workflows
        - Admin site to manage web pages
        - Admin site to manage auto generation for controllers, apis and services
    * Framework documentation
        - Self documentation for apis(swagger and postman outputs)
        - PhpDoc for all files
    * Code coverage
        - 100% tests coverage

