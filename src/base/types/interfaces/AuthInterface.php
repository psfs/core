<?php

namespace PSFS\base\types\interfaces;

/**
 * Interface AuthInterface
 * @package PSFS\base\types\interfaces
 */
interface AuthInterface
{
    /**
     * @return boolean
     */
    function isLogged();

    /**
     * @return boolean
     */
    function isAdmin();

    /**
     * @param string $action
     * @return boolean
     */
    function canDo($action);
}
