<?php

namespace PSFS\base\types;

use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\helpers\SecurityHelper;
use PSFS\base\types\helpers\ServiceHelper;
use PSFS\base\types\traits\CurlTrait;

/**
 * Class CurlService
 * @package PSFS\base\types
 */
abstract class CurlService extends SimpleService
{
    use CurlTrait;

    const PSFS_TRACK_HEADER = 'X-PSFS-UID';

    public function init()
    {
        parent::init();
        $this->clearContext();
    }

    /**
     * @return mixed
     */
    public function getCallInfo()
    {
        return $this->getInfo();
    }

    public function isJson()
    {
        return $this->getIsJson();
    }

    public function isMultipart()
    {
        return $this->getIsMultipart();
    }

    /**
     * Add request param
     *
     * @param $key
     * @param null $value
     *
     * @return CurlService
     */
    public function addOption($key, $value = NULL)
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * @param $header
     * @param null $content
     *
     * @return $this
     */
    public function addHeader($header, $content = NULL)
    {
        $this->headers[$header] = $content;
        return $this;
    }

    /**
     * Generate auth header
     * @param string $secret
     * @param string $module
     */
    protected function addRequestToken($secret, $module = 'PSFS')
    {
        $this->addHeader('X-PSFS-SEC-TOKEN', SecurityHelper::generateToken($secret, $module));
    }

    /**
     * Add basic auth header to curl resquest
     * @param string $user
     * @param string $pass
     */
    protected function addAuthHeader($user, $pass)
    {
        $this->addOption(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $this->addOption(CURLOPT_USERPWD, "$user:$pass");
    }

    protected function applyOptions()
    {
        if (count($this->getOptions())) {
            curl_setopt_array($this->getCon(), $this->getOptions());
        }
    }

    protected function applyHeaders()
    {
        $headers = [];
        foreach ($this->getHeaders() as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        $headers[self::PSFS_TRACK_HEADER] = Logger::getUid();
        if (count($headers)) {
            curl_setopt($this->getCon(), CURLOPT_HTTPHEADER, $headers);
        }
    }

    /**
     * @return int
     */
    private function parseServiceType()
    {
        if ($this->isJson()) {
            return ServiceHelper::TYPE_JSON;
        }
        if ($this->isMultipart()) {
            return ServiceHelper::TYPE_MULTIPART;
        }
        return ServiceHelper::TYPE_HTTP;
    }

    protected function setDefaults()
    {
        $serviceType = $this->parseServiceType();
        $this->addOption(CURLOPT_CUSTOMREQUEST, strtoupper($this->type));
        switch (strtoupper($this->type)) {
            case Request::VERB_GET:
                if (!empty($this->params)) {
                    $sep = false === strpos($this->getUrl(), '?') ? '?' : '';
                    $this->setUrl($this->getUrl() . $sep . http_build_query($this->getParams()), false);
                }
                break;
            case Request::VERB_POST:
            case Request::VERB_PATCH:
            case Request::VERB_PUT:
                $this->addOption(CURLOPT_POSTFIELDS, ServiceHelper::parseRawData($serviceType, $this->getParams()));
                break;
        }

        $this->applyCurlBehavior(
            array_key_exists(CURLOPT_RETURNTRANSFER, $this->options) ? (bool)$this->options[CURLOPT_RETURNTRANSFER] : true,
            array_key_exists(CURLOPT_FOLLOWLOCATION, $this->options) ? (bool)$this->options[CURLOPT_FOLLOWLOCATION] : true,
            array_key_exists(CURLOPT_SSL_VERIFYHOST, $this->options) ? (bool)$this->options[CURLOPT_SSL_VERIFYHOST] : false,
            array_key_exists(CURLOPT_SSL_VERIFYPEER, $this->options) ? (bool)$this->options[CURLOPT_SSL_VERIFYPEER] : false
        );
    }

    public function callSrv()
    {
        $this->setDefaults();
        $this->applyOptions();
        $this->applyHeaders();
        $logLevel = strtolower(Config::getParam('log.level', 'notice'));
        $verbose = null;
        if ('debug' === $logLevel) {
            $verbose = $this->initVerboseMode();
        }
        $result = curl_exec($this->getCon());
        $this->setResult($this->isJson() ? json_decode($result, true) : $result);
        if ('debug' === $logLevel && is_resource($verbose)) {
            $this->dumpVerboseLogs($verbose);
        }
        Logger::log($this->getUrl() . ' response: ', LOG_DEBUG, is_array($this->result) ? $this->result : [$this->result]);
        $this->info = array_merge($this->info, curl_getinfo($this->con));
    }

    /**
     * @param bool $returnTransfer
     * @param bool $followLocation
     * @param bool $sslVerifyHost
     * @param bool $sslVerifyPeer
     */
    protected function applyCurlBehavior($returnTransfer = true, $followLocation = true, $sslVerifyHost = false, $sslVerifyPeer = false)
    {
        $this->addOption(CURLOPT_RETURNTRANSFER, Config::getParam('curl.returnTransfer', $returnTransfer));
        $this->addOption(CURLOPT_FOLLOWLOCATION, Config::getParam('curl.followLocation', $followLocation));
        $this->addOption(CURLOPT_SSL_VERIFYHOST, Config::getParam('curl.sslVerifyHost', $sslVerifyHost));
        $this->addOption(CURLOPT_SSL_VERIFYPEER, Config::getParam('curl.sslVerifyPeer', $sslVerifyPeer));
    }

    /**
     * @return resource
     */
    protected function initVerboseMode()
    {
        curl_setopt($this->getCon(), CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'wb+');
        curl_setopt($this->getCon(), CURLOPT_STDERR, $verbose);
        return $verbose;
    }

    /**
     * @param resource $verbose
     */
    protected function dumpVerboseLogs($verbose)
    {
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        Logger::log($verboseLog, LOG_DEBUG, [
            'headers' => $this->getHeaders(),
            'options' => $this->getOptions(),
            'url' => $this->getUrl(),
        ]);
        $this->info['verbose'] = $verboseLog;
    }
}
