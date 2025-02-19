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
    const PSFS_AUTH_HEADER = 'X-PSFS-SEC-TOKEN';

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
     * @param $header
     * @param $content
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
        $this->addHeader(self::PSFS_AUTH_HEADER, SecurityHelper::generateToken($secret, $module));
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
    protected function parseServiceType()
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
                    $sep = !str_contains($this->getUrl(), '?') ? '?' : '';
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
            !array_key_exists(CURLOPT_RETURNTRANSFER, $this->options) || (bool)$this->options[CURLOPT_RETURNTRANSFER],
            !array_key_exists(CURLOPT_FOLLOWLOCATION, $this->options) || (bool)$this->options[CURLOPT_FOLLOWLOCATION],
            array_key_exists(CURLOPT_SSL_VERIFYPEER, $this->options) ? (int)$this->options[CURLOPT_SSL_VERIFYPEER] : 0
        );
    }

    public function callSrv()
    {
        $this->setDefaults();
        $this->applyOptions();
        $this->applyHeaders();
        $verbose = null;
        if ($this->isDebug()) {
            $verbose = $this->initVerboseMode();
        }
        $this->setRawResult(curl_exec($this->getCon()));
        $this->setResult($this->isJson() ? json_decode($this->getRawResult(), true) : $this->getRawResult());
        if ($this->isDebug() && is_resource($verbose)) {
            $this->dumpVerboseLogs($verbose);
        }
        Logger::log($this->getUrl() . ' response: ', LOG_DEBUG, is_array($this->getRawResult()) ? $this->getRawResult() : [$this->getRawResult()]);
        $this->info = array_merge($this->info, curl_getinfo($this->con));
    }

    /**
     * @param bool $returnTransfer
     * @param bool $followLocation
     * @param bool $sslVerifyPeer
     */
    protected function applyCurlBehavior($returnTransfer = true, $followLocation = true, $sslVerifyPeer = false)
    {
        $this->addOption(CURLOPT_RETURNTRANSFER, Config::getParam('curl.returnTransfer', $returnTransfer));
        $this->addOption(CURLOPT_FOLLOWLOCATION, Config::getParam('curl.followLocation', $followLocation));
        $this->addOption(CURLOPT_SSL_VERIFYHOST, Config::getParam('curl.followLocation', Config::getParam('debug') ? 0 : 2));
        $this->addOption(CURLOPT_SSL_VERIFYPEER, Config::getParam('curl.sslVerifyPeer', $sslVerifyPeer));
    }

    /**
     * @return resource
     */
    protected function initVerboseMode()
    {
        curl_setopt($this->getCon(), CURLINFO_HEADER_OUT, true);
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
