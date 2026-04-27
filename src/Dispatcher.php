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
use PSFS\base\exception\RequestTerminationException;
use PSFS\base\exception\RouterException;
use PSFS\base\exception\SecurityException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
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

    #[Injectable(class: Security::class)]
    protected $security;

    #[Injectable(class: Router::class)]
    protected $router;

    #[Injectable(class: Config::class)]
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
        $targetUri = DispatcherRuntimeHelper::resolveTargetUri($uri, $this->actualUri);
        $this->actualUri = DispatcherRuntimeHelper::resolveActualRequestUri(
            $uri,
            Request::getInstance()->getServer('REQUEST_URI', '')
        );

        try {
            if ($this->config->isConfigured()) {
                return $this->runConfiguredRequest($targetUri);
            }
            return $this->runSetupRequest($targetUri, $uri);
        } catch (RequestTerminationException $terminationException) {
            throw $terminationException;
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

    private function runConfiguredRequest(string $targetUri): mixed
    {
        RequestHelper::checkCORS();
        if (DispatcherRuntimeHelper::isUnitTestExecution()) {
            return $this->router->execute($targetUri);
        }
        if (DispatcherRuntimeHelper::isFileTargetUri($targetUri)) {
            return $this->router->httpNotFound();
        }
        return $this->router->execute($targetUri);
    }

    private function runSetupRequest(string $targetUri, mixed $uri): mixed
    {
        if (DispatcherRuntimeHelper::canRunSetupRoute($targetUri, $uri, self::SETUP_ALLOWED_PATHS)) {
            return $this->router->execute($targetUri);
        }
        if ($this->mustForceAdminSetup()) {
            return UserController::showAdminManager();
        }
        return ConfigController::getInstance()->config();
    }

    private function mustForceAdminSetup(): bool
    {
        return !defined('PSFS_UNIT_TESTING_EXECUTION') && empty($this->security->getAdmins());
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
