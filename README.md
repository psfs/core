# PSFS
[![Build Status](https://travis-ci.org/psfs/core.svg?branch=master)](https://travis-ci.org/psfs/core)
[![Latest Stable Version](https://poser.pugx.org/psfs/core/v/stable)](https://packagist.org/packages/psfs/core) 
[![Total Downloads](https://poser.pugx.org/psfs/core/downloads)](https://packagist.org/packages/psfs/core) 
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/psfs/core/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/psfs/core/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)

## Framework Php Simple Fast & Secure

Requirements:

* php 5.6+, 7.0+
* ext-gettext
* ext-json
* ext-curl

Components that PSFS install:

```
"propel/propel": "^2.0"
"symfony/console": "@stable"
"symfony/finder": "@stable"
"twig/twig": "@stable"
"twig/extensions": "@stable"
"monolog/monolog": "@stable"
"matthiasmullie/minify": "@stable"
```

How to install using composer:

Install composer via: [GetComposer](https://getcomposer.org/download/)
   
```
php composer.phar require psfs/core
./vendor/bin/psfs psfs:create:root
php -S localhost:8080 -t ./html
```

RoadMap:

    * Session management
        - Session engines(mongodb, filesystem, mysql...)
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
    * Testing
        - 100% tests coverage
    * Containers
        - Docker
        - Kubernetes

