{
  "name": "__PACKAGE_NAME__",
  "description": "__DESCRIPTION__",
  "type": "project",
  "license": "__LICENSE__",
  "authors": [
    {
      "name": "__AUTHOR__"
    }
  ],
  "require": {
    "php": ">=8.3",
    "psfs/core": "^2.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  },
  "scripts": {
    "psfs:list": "php vendor/bin/psfs list",
    "psfs:create:root": "php vendor/bin/psfs psfs:create:root"
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
