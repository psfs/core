<?php

namespace PSFS\base;

use PSFS\base\types\CurlService;

/**
 * Class Service
 * @package PSFS\base
 */
class Service extends CurlService
{
    const CTYPE_JSON = 'application/json';
    const CTYPE_MULTIPART = 'multipart/form-data';
    const CTYPE_FORM = 'application/x-www-form-urlencoded';
    const CTYPE_PLAIN = 'text/plain';
}
