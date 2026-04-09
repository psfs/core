<?php

namespace PSFS\runtime\swoole;

use PSFS\base\exception\RequestTerminationException;
use PSFS\Dispatcher;
use Throwable;

class SwooleRequestExecutor
{
    /**
     * @return array{0:int,1:string}
     */
    public function execute(string $requestUri): array
    {
        $statusCode = 200;
        $body = '';
        ob_start();
        try {
            $dispatcher = Dispatcher::getInstance();
            $result = $dispatcher->run($requestUri);
            $buffered = (string)ob_get_clean();
            $body = $buffered;
            if ($body === '' && is_string($result)) {
                $body = $result;
            }
        } catch (RequestTerminationException) {
            $body = (string)ob_get_clean();
        } catch (Throwable) {
            $body = (string)ob_get_clean();
            $statusCode = 500;
            if ($body === '') {
                $body = 'Internal server error';
            }
        } finally {
            if (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        return [$statusCode, $body];
    }
}
