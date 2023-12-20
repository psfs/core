# PSFS
[![Build Status](https://travis-ci.org/psfs/core.svg?branch=master)](https://travis-ci.org/psfs/core)
[![Latest Stable Version](https://poser.pugx.org/psfs/core/v/stable)](https://packagist.org/packages/psfs/core) 
[![Total Downloads](https://poser.pugx.org/psfs/core/downloads)](https://packagist.org/packages/psfs/core) 
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/psfs/core/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/psfs/core/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/psfs/core/?branch=master)

## Framework Php Simple Fast & Secure

Requirements:

* php 8.2+
* ext-gettext
* ext-json
* ext-curl
* ext-gmp
* ext-fileinfo

### Components that PSFS install:

```
"propel/propel": "2.0.0-beta2",
"symfony/console": "v6.x",
"symfony/finder": "v6.x",
"symfony/translation": "v6.x",
"twig/twig": "3.6.0",
"monolog/monolog": "3.x",
"matthiasmullie/minify": "1.3.70"
```

### How to install using composer:

Install composer via: [GetComposer](https://getcomposer.org/download/)
   
```
composer require psfs/core
./vendor/bin/psfs psfs:create:root
php -S 0.0.0.0:8080 -t ./html
```

### How to use with Docker
```
docker-compose up .
```
Your could use some environment variables to manage the docker containers
```
- APP_ENVIRONMENT: (local|dev|...|prod) Define the staging for the run environment
- HOST_PORT: 8001 Define the port where you could expose the server
- DEBUG: -xdebug Loads a docker image with xdebug installed and configured, if empty it loads a default php image
```


RoadMap:

    * Framework documentation
        - PhpDoc for all files
    * Testing
        - 100% tests coverage

