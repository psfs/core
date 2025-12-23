<?php

require_once __DIR__ . "/../vendor/autoload.php";

use PSFS\base\SingletonRegistry;
use PSFS\Dispatcher;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

$port = 9501;

$server = new Server("0.0.0.0", $port, SWOOLE_BASE);
$server->set([
    'worker_num' => 4,          // varios workers para ver diferencias entre ellos
    'max_request' => 5000,         // no reinicia workers
    'log_level' => SWOOLE_LOG_INFO,
    'daemonize' => 0,
]);

$server->on("request", function (Request $req, Response $res) {
    $req->server[SingletonRegistry::CONTEXT_SESSION] = bin2hex(random_bytes(16));
    $_SERVER = [];
    foreach ($req->server as $k => $v) {
        $_SERVER[strtoupper($k)] = $v;
    }
    register_shutdown_function(function () {
        SingletonRegistry::clear();
    });

    try {
        ob_start(); // por si hay output accidental
        // tu framework procesando la request
        Dispatcher::getInstance()->run();
    } catch (\Throwable $e) {
        // tu error handler
        $res->status($e->getCode());
    } finally {
        if (!$res->isWritable()) {
            return; // exit/die ya cortó el ciclo
        }
        // Normal end
        $res->end(ob_get_clean());
        SingletonRegistry::clear();
    }
});

echo "Servidor Swoole escuchando en http://127.0.0.1:$port\n";

$server->start();
