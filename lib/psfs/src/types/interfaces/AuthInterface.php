<?php

namespace PSFS\types\interfaces;

interface AuthInterface{
    function isLogged();
    function isAdmin();
    function canDo($action);
}