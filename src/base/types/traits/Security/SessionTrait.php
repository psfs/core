<?php
namespace PSFS\base\types\traits\Security;

use PSFS\base\Logger;

/**
 * Trait SessionTrait
 * @package PSFS\base\types\traits\Security
 */
trait SessionTrait {
    /**
     * @var array $session
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
        $data = NULL;
        if ($this->hasSessionKey($key)) {
            $data = $this->session[$key];
        }

        return $data;
    }

    public function hasSessionKey($key) {
        $exists = false;
        if(array_key_exists($key, $this->session)) {
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
    public function setSessionKey($key, $data = NULL)
    {
        $this->session[$key] = $data;

        return $this;
    }

    protected function initSession() {
        if (PHP_SESSION_NONE === session_status() && !headers_sent()) {
            session_start();
        }
        // Fix for phpunits
        if(!isset($_SESSION)) {
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
    public function updateSession($closeSession = FALSE)
    {
        Logger::log('Update session');
        $_SESSION = $this->session;
        $_SESSION[self::USER_ID_TOKEN] = serialize($this->user);
        $_SESSION[self::ADMIN_ID_TOKEN] = serialize($this->admin);
        if ($closeSession) {
            Logger::log('Close session');
            /** @scrutinizer ignore-unhandled */ @session_write_close();
            /** @scrutinizer ignore-unhandled */ @session_start();
        }
        Logger::log('Session updated');
        return $this;
    }

    public function closeSession()
    {
        unset($_SESSION);
        /** @scrutinizer ignore-unhandled */ @session_destroy();
        /** @scrutinizer ignore-unhandled */ @session_regenerate_id(TRUE);
        /** @scrutinizer ignore-unhandled */ @session_start();
    }

}
