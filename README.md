# PSFS
[![Build Status](https://travis-ci.org/psfs/core.svg?branch=master)](https://travis-ci.org/psfs/core)
[![Latest Stable Version](https://poser.pugx.org/psfs/core/v/stable)](https://packagist.org/packages/psfs/core) 
[![Total Downloads](https://poser.pugx.org/psfs/core/downloads)](https://packagist.org/packages/psfs/core) 
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/psfs/core/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/psfs/core/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)

## Framework Php Simple Fast & Secure

Requirements:

* php 7.1+
* ext-gettext
* ext-json
* ext-curl
* ext-gmp

Components that PSFS install:

```
"propel/propel": "^2.0"
"symfony/console": "@v4.4.7"
"symfony/finder": "@v4v4v7"
"twig/twig": "@v2.12.5"
"twig/extensions": "@v1.5.4"
"monolog/monolog": "@1.25.3"
"matthiasmullie/minify": "@1.3.63"
```

How to install using composer:

Install composer via: [GetComposer](https://getcomposer.org/download/)
   
```
php composer.phar require psfs/core
./vendor/bin/psfs psfs:create:root
php -S localhost:8080 -t ./html
```

RoadMap:

    * Framework documentation
        - PhpDoc for all files
    * Testing
        - 100% tests coverage
    * Containers
        - Docker

