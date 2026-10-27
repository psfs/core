<?php

namespace PSFS\base\types\helpers;

use Monolog\Level;
use PSFS\base\config\Config;
use PSFS\base\Logger;

class LogHelper
{
    public const CHANNEL_PHP = 'php';
    public const CHANNEL_DEPRECATION = 'deprecation';
    public const CHANNEL_SECURITY = 'security';
    public const DEFAULT_LEVEL = 'WARNING';
    /** Built-in thresholds for channels that must not follow the global level */
    public const CHANNEL_DEFAULT_LEVELS = [
        self::CHANNEL_DEPRECATION => 'ERROR',
        self::CHANNEL_SECURITY => 'WARNING',
    ];
    public const DEFAULT_DEPRECATION_MODE = 'once';
    private const DEPRECATION_OFF = 'off';
    private const DEPRECATION_ONCE = self::DEFAULT_DEPRECATION_MODE;
    private const MAX_SEEN_DEPRECATIONS = 1000;
    /** E_STRICT value; the constant itself is deprecated since PHP 8.4 */
    private const E_STRICT_LEGACY = 2048;

    /** @var array<string,true> */
    private static array $seenDeprecations = [];

    /**
     * @param string $currentLogLevel
     * @param Level $level
     * @return bool
     */
    public static function checkLogLevel(string $currentLogLevel, \Monolog\Level $level = \Monolog\Level::Notice): bool
    {
        return self::parseLevel($currentLogLevel)->value <= $level->value;
    }

    /**
     * Translates a configured level name into a Monolog level (the default level for unknown names)
     * @param string $name
     * @return Level
     */
    public static function parseLevel(string $name): Level
    {
        return match (strtoupper(trim($name))) {
            'DEBUG' => Level::Debug,
            'INFO' => Level::Info,
            'WARNING' => Level::Warning,
            'ERROR' => Level::Error,
            'CRITICAL' => Level::Critical,
            'ALERT' => Level::Alert,
            'EMERGENCY' => Level::Emergency,
            'NOTICE' => Level::Notice,
            default => self::parseLevel(self::DEFAULT_LEVEL),
        };
    }

    /**
     * Reads a log setting. A non-empty PSFS_<KEY> environment variable (log.level.php -> PSFS_LOG_LEVEL_PHP)
     * wins over the PSFS configuration, so containers can be tuned without touching config files.
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public static function setting(string $key, ?string $default = null): ?string
    {
        $env = getenv('PSFS_' . strtoupper(str_replace('.', '_', $key)));
        if (false !== $env && '' !== trim($env)) {
            return $env;
        }
        $value = Config::getParam($key);
        return null !== $value && '' !== trim((string)$value) ? (string)$value : $default;
    }

    /**
     * Resolves the configured threshold for a channel: log.level.<channel>, then the built-in channel
     * default (deprecation, security) and finally log.level
     * @param string|null $channel
     * @return string
     */
    public static function resolveChannelLevel(?string $channel = null): string
    {
        $default = (string)self::setting('log.level', self::DEFAULT_LEVEL);
        if (null === $channel || '' === $channel) {
            return strtoupper($default);
        }
        $channel = strtolower($channel);
        $channelLevel = (string)self::setting('log.level.' . $channel, '');
        if ('' === trim($channelLevel)) {
            $channelLevel = self::CHANNEL_DEFAULT_LEVELS[$channel] ?? $default;
        }
        return strtoupper($channelLevel);
    }

    /**
     * @param Level $level
     * @param string|null $channel
     * @return bool
     */
    public static function isEnabled(Level $level, ?string $channel = null): bool
    {
        return self::checkLogLevel(self::resolveChannelLevel($channel), $level);
    }

    /**
     * Maps a PHP error number to its log channel and Monolog level
     * @param int $errno
     * @return array{0:string,1:Level}
     */
    public static function mapPhpError(int $errno): array
    {
        return match (true) {
            0 !== ($errno & (E_DEPRECATED | E_USER_DEPRECATED)) => [self::CHANNEL_DEPRECATION, Level::Notice],
            0 !== ($errno & (E_NOTICE | E_USER_NOTICE | self::E_STRICT_LEGACY)) => [self::CHANNEL_PHP, Level::Notice],
            0 !== ($errno & (E_WARNING | E_USER_WARNING | E_CORE_WARNING | E_COMPILE_WARNING)) => [self::CHANNEL_PHP, Level::Warning],
            default => [self::CHANNEL_PHP, Level::Critical],
        };
    }

    /**
     * Logs a PHP error in its own channel, deduplicating deprecations per process
     * @param int $errno
     * @param string $message
     * @param string $file
     * @param int $line
     * @return Level|null level used, null when the record has been skipped
     */
    public static function reportPhpError(int $errno, string $message, string $file = '', int $line = 0): ?Level
    {
        [$channel, $level] = self::mapPhpError($errno);
        if (self::CHANNEL_DEPRECATION === $channel && !self::acceptDeprecation($message, $file, $line)) {
            return null;
        }
        Logger::log(
            $message,
            self::toSyslog($level),
            ['file' => $file, 'line' => $line, 'errno' => $errno],
            false,
            $channel
        );
        return $level;
    }

    /**
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     */
    private static function acceptDeprecation(string $message, string $file, int $line): bool
    {
        $mode = strtolower((string)self::setting('log.deprecation.mode', self::DEFAULT_DEPRECATION_MODE));
        if (self::DEPRECATION_OFF === $mode) {
            return false;
        }
        if (self::DEPRECATION_ONCE !== $mode) {
            return true;
        }
        $key = md5($message . '|' . $file . '|' . $line);
        if (isset(self::$seenDeprecations[$key])) {
            return false;
        }
        if (count(self::$seenDeprecations) >= self::MAX_SEEN_DEPRECATIONS) {
            self::$seenDeprecations = [];
        }
        self::$seenDeprecations[$key] = true;
        return true;
    }

    public static function resetSeenDeprecations(): void
    {
        self::$seenDeprecations = [];
    }

    /**
     * @param Level $level
     * @return int
     */
    private static function toSyslog(Level $level): int
    {
        return match ($level) {
            Level::Debug => LOG_DEBUG,
            Level::Info => LOG_INFO,
            Level::Notice => LOG_NOTICE,
            Level::Warning => LOG_WARNING,
            Level::Error => LOG_ERR,
            Level::Critical => LOG_CRIT,
            Level::Alert => LOG_ALERT,
            Level::Emergency => LOG_EMERG,
        };
    }

    /**
     * @param $logger
     *
     * @return mixed
     */
    public static function cleanLoggerName($logger): mixed
    {
        $logger = str_replace(' ', '', $logger);
        return preg_replace("/\\\\/", ".", $logger);
    }

    /**
     * @param array $context
     * @return array
     */
    public static function addMinimalContext(array $context = []): array
    {
        $context['uri'] = ServerHelper::getServerValue('REQUEST_URI', 'Unknow');
        $context['method'] = ServerHelper::getServerValue('REQUEST_METHOD', 'Unknow');
        if (null !== ServerHelper::getServerValue('HTTP_X_PSFS_UID')) {
            $context['uid'] = ServerHelper::getServerValue('HTTP_X_PSFS_UID');
        }
        return $context;
    }

    /**
     * @param int $type
     * @return \Monolog\Level
     */
    public static function calculateLogLevel(int $type): \Monolog\Level
    {
        switch ($type) {
            case LOG_DEBUG:
                $level = \Monolog\Level::Debug;
                break;
            case LOG_WARNING:
                $level = \Monolog\Level::Warning;
                break;
            case LOG_CRIT:
                $level = \Monolog\Level::Critical;
                break;
            case LOG_ERR:
                $level = \Monolog\Level::Error;
                break;
            case LOG_INFO:
                $level = \Monolog\Level::Info;
                break;
            case LOG_EMERG:
                $level = \Monolog\Level::Emergency;
                break;
            case LOG_ALERT:
                $level = \Monolog\Level::Alert;
                break;
            default:
                $level = \Monolog\Level::Notice;
                break;
        }
        return $level;
    }
}
