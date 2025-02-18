<?php

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Protocols\Http\ServerSentEvents;
use Workerman\Timer;
use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

if (!defined('STDIN')) define('STDIN', fopen('php://stdin', 'r'));
if (!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'w'));
if (!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));

$worker = new Worker('http://0.0.0.0:8080');

$worker->onMessage = function (TcpConnection $connection, Request $request) {
    match ($request->path()) {
        '/' => $connection->send('Hello Chance'),
        '/get' => $connection->send(json_encode($request->get())),
        '/post' => $connection->send(json_encode($request->post())),
        '/header' => $connection->send($request->header('foo')),
        '/setSession' => (function () use ($connection, $request) {
            $request->session()->set('foo', 'bar');
            $connection->send('');
        })(),
        '/session' => $connection->send($request->session()->pull('foo')),
        '/sse' => (function () use ($connection) {
            $connection->send(new Response(200, ['Content-Type' => 'text/event-stream'], "\r\n"));
            $i = 0;
            $timer_id = Timer::add(0.001, function () use ($connection, &$timer_id, &$i) {
                if ($connection->getStatus() !== TcpConnection::STATUS_ESTABLISHED) {
                    Timer::del($timer_id);
                    return;
                }
                if ($i >= 5) {
                    Timer::del($timer_id);
                    $connection->close();
                    return;
                }
                $i++;
                $connection->send(new ServerSentEvents(['data' => "hello$i"]));
            });
        })(),
        '/file' => $connection->send(json_encode($request->file('file'))),
        default => $connection->send(new Response(404, [], '404 not found'))
    };
};

Worker::$command = 'start';
Worker::runAll();