<?php

namespace PSFS\base\types\traits\Security;

use PSFS\base\Logger;
use PSFS\base\runtime\RuntimeMode;
use PSFS\base\types\helpers\AuthHelper;
use PSFS\base\types\helpers\CompatHelper;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\base\types\helpers\SessionCookieHelper;

/**
 * @package PSFS\base\types\traits\Security
 */
trait SessionTrait
{
    /**
     * Keys the framework writes on every request for its own bookkeeping. Setting them never creates a PHP session:
     * a visitor without a session cookie has nothing to remember, and a session per request costs a round trip to the
     * session store (Redis) and a Set-Cookie header on every response, API calls included.
     */
    private const EPHEMERAL_SESSION_KEYS = [
        '__FLASH_CLEAR__',
        'lastRequest',
        '__CACHE__',
        'config.cache.var',
        '__PSFS_TRANSLATIONS__',
        '__PSFS_LOCALE_VERSION__',
        '__PSFS_SESSION_LANG_SELECTED__',
        '__PSFS_SESSION_LOCALE_SELECTED__',
    ];

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
        if (null !== $data && !in_array($key, self::EPHEMERAL_SESSION_KEYS, true)) {
            $this->ensureSessionStarted();
        }

        return $this;
    }

    /**
     * Keeps a value for the rest of the request, and in the session only if the request already has one (it is written
     * with the rest of the session when the request ends). Unlike setSessionKey it never creates a session.
     */
    protected function rememberInSession(string $key, mixed $data): void
    {
        $this->session[$key] = $data;
    }

    /**
     * The session is created when something has to be remembered (a login, a token, a message, a chosen language),
     * not for every visitor: see session.lazy. A request that carries a session cookie always resumes its session.
     */
    protected function initSession()
    {
        if (PHP_SESSION_NONE === session_status() && $this->canStartSession() && $this->shouldResumeSession()) {
            $this->startPhpSession();
        }
        // Fix for phpunits
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $this->session = null === $_SESSION ? [] : $_SESSION;
    }

    /**
     * Makes sure the visitor has a PHP session, keeping what the request collected so far. It has to run before the
     * response headers are sent, because the session cookie is one of them.
     */
    public function ensureSessionStarted(): static
    {
        if (PHP_SESSION_NONE === session_status() && $this->canStartSession()) {
            $this->startPhpSession();
            $_SESSION = is_array($this->session) ? $this->session : [];
        }

        return $this;
    }

    private function canStartSession(): bool
    {
        return RuntimeMode::isLongRunningServer() || !headers_sent();
    }

    private function startPhpSession(): void
    {
        SessionCookieHelper::apply(ResponseHelper::isSecureRequest());
        session_start();
    }

    private function shouldResumeSession(): bool
    {
        if (!CompatHelper::flag('session.lazy', true)) {
            return true;
        }
        $name = session_name();

        return is_string($name) && '' !== (string)($_COOKIE[$name] ?? '');
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
        if ($closeSession && PHP_SESSION_ACTIVE === session_status()) {
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
        $canStartSession = $this->canStartSession();
        if (PHP_SESSION_NONE === session_status() && !$this->shouldResumeSession()) {
            // there is no session to close: the visitor never had one
            $_SESSION = [];
            $this->session = $_SESSION;
            return;
        }
        if (PHP_SESSION_NONE === session_status() && $canStartSession) {
            @session_start();
        }

        if (PHP_SESSION_ACTIVE === session_status()) {
            session_unset();
            if (@session_regenerate_id(true) === false) {
                Logger::log('[SessionTrait::closeSession] Unable to regenerate session id');
            }
        } elseif ($canStartSession) {
            $started = @session_start();
            if (!$started) {
                Logger::log('[SessionTrait::closeSession] Unable to start session');
            }
        }

        $_SESSION = [];
        $this->session = $_SESSION;
    }

}
