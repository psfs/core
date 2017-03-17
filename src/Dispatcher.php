<?php
/**
 * @author Fran López <fran.lopez84@hotmail.es>
 * @version 1.0
 */

namespace PSFS;

use PSFS\base\exception\AdminCredentialsException;
use PSFS\base\exception\RouterException;
use PSFS\base\exception\SecurityException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Security;
use PSFS\base\Singleton;
use PSFS\base\types\helpers\I18nHelper;
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
     * @Inyectable
     * @var \PSFS\base\Security $security
     */
    protected $security;
    /**
     * @Inyectable
     * @var \PSFS\base\Router $router
     */
    protected $router;
    /**
     * @Inyectable
     * @var \PSFS\base\config\Config $config
     */
    protected $config;

    private $actualUri;

    /**
     * Initializer method
     */
    public function init()
    {
        Logger::log('Dispatcher init');
        parent::init();
        $this->initiateStats();
        I18nHelper::setLocale();
        $this->bindWarningAsExceptions();
        $this->actualUri = Request::getInstance()->getServer("REQUEST_URI");
        Logger::log('End dispatcher init');
    }

    /**
     * Run method
     * @return string HTML
     */
    public function run()
    {
        Logger::log('Begin runner');
        try {
            if ($this->config->isConfigured()) {
                if (!Request::getInstance()->isFile()) {
                    return $this->router->execute($this->actualUri);
                }
            } else {
                return ConfigController::getInstance()->config();
            }
        } catch (AdminCredentialsException $a) {
            return UserController::showAdminManager();
        } catch (SecurityException $s) {
            return Security::getInstance()->notAuthorized($this->actualUri);
        } catch (RouterException $r) {
            return $this->router->httpNotFound($r);
        } catch (\Exception $e) {
            return $this->dumpException($e);
        }
    }

    /**
     * Method that convert an exception to html
     *
     * @param \Exception $e
     *
     * @return string HTML
     */
    protected function dumpException(\Exception $e)
    {
        Logger::log('Starting dump exception');
        $ex = (NULL !== $e->getPrevious()) ? $e->getPrevious() : $e;
        $error = array(
            "error" => $ex->getMessage(),
            "file" => $ex->getFile(),
            "line" => $ex->getLine(),
        );
        Logger::log('Throwing exception', LOG_ERR, $error);
        unset($error);

        return $this->router->httpNotFound($ex);
    }

}
