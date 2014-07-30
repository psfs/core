<?php

namespace PSFS\base\types\interfaces;

interface AuthInterface{
    function isLogged();
    function isAdmin();
    function canDo($action);
}