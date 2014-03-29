<?php
use PSFS\Dispatcher;

require_once(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."config".DIRECTORY_SEPARATOR."bootstrap.php");
Dispatcher::getInstance()->run();