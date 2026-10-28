<?php

namespace PSFS\base\types\helpers;

/**
 * PHP session cookie flags required by the authentication contract: HttpOnly, Path=/, SameSite and Secure on HTTPS.
 *
 * session.cookie.samesite: Lax (default) | Strict | None | legacy (do not set it, deprecated)
 * session.cookie.secure:   auto (default, Secure when the request is HTTPS) | true | false
 */
final class SessionCookieHelper
{
    private const SAMESITE = ['lax' => 'Lax', 'strict' => 'Strict', 'none' => 'None'];

    /**
     * @param array $current session_get_cookie_params()
     * @return array session_set_cookie_params() options
     */
    public static function buildParams(array $current, bool $secureRequest): array
    {
        $secure = match (strtolower(trim((string)LogHelper::setting('session.cookie.secure', 'auto')))) {
            'true', '1', 'on', 'yes' => true,
            'false', '0', 'off', 'no' => false,
            default => $secureRequest,
        };
        $params = [
            'lifetime' => (int)($current['lifetime'] ?? 0),
            'path' => '/',
            'domain' => (string)($current['domain'] ?? ''),
            'secure' => $secure,
            'httponly' => true,
        ];

        $configured = strtolower(trim((string)LogHelper::setting('session.cookie.samesite', 'Lax')));
        if ('legacy' === $configured) {
            CompatHelper::warnOnce('session.samesite', 'session.cookie.samesite=legacy leaves the session cookie without SameSite; this switch will be removed');
            return $params;
        }
        $samesite = self::SAMESITE[$configured] ?? 'Lax';
        if ('None' === $samesite && !$secure) {
            // Browsers reject SameSite=None cookies that are not Secure
            CompatHelper::warnOnce('session.samesite.none', 'session.cookie.samesite=None needs a Secure cookie; using Lax for this request');
            $samesite = 'Lax';
        }
        $params['samesite'] = $samesite;
        return $params;
    }

    /**
     * Must run before session_start()
     */
    public static function apply(bool $secureRequest): bool
    {
        if (PHP_SESSION_NONE !== session_status() || headers_sent()) {
            return false;
        }
        return session_set_cookie_params(self::buildParams(session_get_cookie_params(), $secureRequest));
    }
}
