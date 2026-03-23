<?php
/**
 * @author Fran López <fran.lopez84@hotmail.es>
 * @version 1.0
 */

namespace PSFS;

use PSFS\base\config\Config;
use PSFS\base\events\CloseSessionEvent;
use PSFS\base\exception\AdminCredentialsException;
use PSFS\base\exception\ApiException;
use PSFS\base\exception\RouterException;
use PSFS\base\exception\SecurityException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\EventHelper;
use PSFS\base\types\helpers\I18nHelper;
use PSFS\base\types\helpers\Inspector;
use PSFS\base\types\helpers\RequestHelper;
use PSFS\base\types\helpers\attributes\Injectable;
use PSFS\base\types\traits\SystemTrait;
use PSFS\controller\ConfigController;
use PSFS\controller\UserController;

/**
 * Class Dispatcher
 * @package PSFS
 */
class Dispatcher extends Singleton
{
    use SystemTrait;

    /**
     * @Injectable
     * @var \PSFS\base\Security $security
     */
    #[Injectable]
    protected $security;

    /**
     * @Injectable
     * @var \PSFS\base\Router $router
     */
    #[Injectable]
    protected $router;

    /**
     * @Injectable
     * @var \PSFS\base\config\Config $config
     */
    #[Injectable]
    protected $config;

    private $actualUri;

    /**
     * Setup-time routes that must keep working before the platform is fully configured.
     * They are needed by the config/admin bootstrap UI (JSON helpers and save actions).
     */
    private const SETUP_ALLOWED_PATHS = [
        '/admin/setup',
        '/admin/config',
        '/admin/config/params',
        '/admin/routes/show',
    ];

    /**
     * Initializer method
     * @throws base\exception\GeneratorException
     */
    public function init()
    {
        Config::getInstance();
        Inspector::stats('[Dispatcher] Dispatcher init', Inspector::SCOPE_DEBUG);
        $this->initiateStats();
        parent::init();
        I18nHelper::setLocale();
        $this->bindWarningAsExceptions();
        $this->actualUri = Request::getInstance()->getServer('REQUEST_URI');
        Inspector::stats('[Dispatcher] Dispatcher init end', Inspector::SCOPE_DEBUG);
        EventHelper::addEvent(EventHelper::EVENT_END_REQUEST, CloseSessionEvent::class);
    }

    /**
     * Run method
     * @param string $uri
     * @return string HTML
     * @throws base\exception\GeneratorException
     */
    public function run($uri = null)
    {
        Inspector::stats('[Dispatcher] Begin runner', Inspector::SCOPE_DEBUG);
        $targetUri = $uri ?? $this->actualUri;

        try {
            if ($this->config->isConfigured()) {
                // Check CORS for requests
                RequestHelper::checkCORS();
                if (!Request::getInstance()->isFile()) {
                    return $this->router->execute($targetUri);
                }
            } else {
                if ($this->isSetupRouteAllowed($targetUri)) {
                    return $this->router->execute($targetUri);
                }
                // First boot flow: when there are no admins yet, force admin setup before config wizard.
                if (!defined('PSFS_UNIT_TESTING_EXECUTION') && empty($this->security->getAdmins())) {
                    return UserController::showAdminManager();
                }
                return ConfigController::getInstance()->config();
            }
        } catch (AdminCredentialsException $a) {
            return UserController::showAdminManager();
        } catch (SecurityException $s) {
            return $this->security->notAuthorized($this->actualUri);
        } catch (RouterException $r) {
            return $this->router->httpNotFound($r);
        } catch (ApiException $a) {
            return $this->router->httpNotFound($a, true);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }

        return $this->router->httpNotFound();

    }

    private function isSetupRouteAllowed(?string $uri): bool
    {
        if (!is_string($uri) || '' === $uri) {
            return false;
        }
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || '' === $path) {
            return false;
        }
        $normalizedPath = rtrim($path, '/');
        if ('' === $normalizedPath) {
            $normalizedPath = '/';
        }
        return in_array($normalizedPath, self::SETUP_ALLOWED_PATHS, true);
    }

    protected function handleException(\Exception $exception): string
    {
        Inspector::stats('[Dispatcher] Starting dump exception', Inspector::SCOPE_DEBUG);

        $error = [
            "error" => $exception->getMessage(),
            "file" => $exception->getFile(),
            "line" => $exception->getLine(),
        ];

        Logger::log('Throwing exception', LOG_ERR, $error);
        unset($error);

        return $this->router->httpNotFound($exception);
    }
}

Config::initialize();
