<?php

namespace PSFS\base\types\traits\Security;

use PSFS\base\Logger;
use PSFS\base\types\helpers\AuthHelper;

/**
 * @package PSFS\base\types\traits\Security
 */
trait SessionTrait
{
    /**
     * @var array
     */
    protected $session;

    /**
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getSessionKey($key)
    {
        $data = null;
        if ($this->hasSessionKey($key)) {
            $data = $this->session[$key];
        }

        return $data;
    }

    public function hasSessionKey($key)
    {
        $exists = false;
        if (array_key_exists($key, $this->session)) {
            $exists = true;
        }
        return $exists;
    }

    /**
     *
     * @param string $key
     * @param mixed $data
     *
     * @return $this
     */
    public function setSessionKey($key, $data = null)
    {
        $this->session[$key] = $data;

        return $this;
    }

    protected function initSession()
    {
        if (PHP_SESSION_NONE === session_status() && !headers_sent()) {
            session_start();
        }
        // Fix for phpunits
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $this->session = null === $_SESSION ? [] : $_SESSION;
    }

    /**
     *
     * @param boolean $closeSession
     *
     * @return $this
     */
    public function updateSession($closeSession = false)
    {
        Logger::log('Update session');
        $_SESSION = $this->session;
        $_SESSION[AuthHelper::USER_ID_TOKEN] = is_array($this->user) ? $this->user : null;
        $_SESSION[AuthHelper::ADMIN_ID_TOKEN] = is_array($this->admin) ? $this->admin : null;
        if ($closeSession) {
            Logger::log('Close session');

            if (@session_write_close() === false) {
                Logger::log('[SessionTrait::updateSession] Unable to close session');
            }

            if (@session_start() === false) {
                Logger::log('[SessionTrait::updateSession] Unable to start session');
            }
        }
        Logger::log('Session updated');
        return $this;
    }

    public function closeSession()
    {
        unset($_SESSION);

        if (@session_destroy() === false) {
            Logger::log('[SessionTrait::closeSession] Unable to destroy session');
        }

        if (@session_regenerate_id(true) === false) {
            Logger::log('[SessionTrait::closeSession] Unable to regenerate session id');
        }

        if (@session_start() === false) {
            Logger::log('[SessionTrait::closeSession] Unable to start session');
        }
    }

}
