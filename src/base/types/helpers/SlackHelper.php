<?php

namespace PSFS\base\types\helpers;

use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Service;

/**
 * Class SlackHelper
 * @package PSFS\base\types\helpers
 */
class SlackHelper extends Service
{
    /**
     * @var string
     */
    private static $hookUrl;

    public function __construct()
    {
        self::$hookUrl = Config::getParam('log.slack.hook');
        parent::__construct();
    }

    public function trace($message, $file, $line, $info = []): void
    {
        $this->setUrl(self::$hookUrl);
        $this->setIsJson();
        $this->setType(Request::VERB_POST);
        $request = Request::getInstance();
        $this->setParams([
            'text' => 'PSFS Error notifier',
            'attachments' => [
                [
                    "author_name" => $request->getRootUrl(true),
                    "text" => $file . ($line !== '' ? ' [' . $line . ']' : ''),
                    "color" => Config::getParam('debug', true) ? 'warning' : "danger",
                    "title" => $message,
                    'fallback' => 'PSFS Error notifier',
                    "fields" => [
                        [
                            "title" => "Url",
                            "value" => $request->getRequestUri(),
                            "short" => false
                        ],
                        [
                            "title" => "Method",
                            "value" => $request->getMethod(),
                            "short" => false
                        ],
                        [
                            "title" => "Payload",
                            "value" => json_encode($request->getData(), JSON_UNESCAPED_UNICODE),
                            "short" => false
                        ],
                        [
                            "title" => "ExtraInfo",
                            "value" => json_encode($info, JSON_UNESCAPED_UNICODE),
                            "short" => false
                        ]
                    ],
                    "ts" => time(),
                ]
            ]
        ]);
        $this->callSrv();
    }
}
