<?php
require __DIR__ . '/../vendor/autoload.php';
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

$worker = new Worker();
$worker->onWorkerStart = function(){
    $mqtt = new Workerman\Mqtt\Client('mqtt://127.0.0.1:1883');
    $mqtt->onConnect = function($mqtt) {

        $room = 'workerman';
        echo "connect mqtt success!\r\n";

        $mqtt->subscribe($room, null, function(){
            echo "join room success! type something to talk!\r\n";
        });

        $mqtt->onMessage = function($room, $message){
            echo "room[$room]:", $message, "\r\n";
        };

        // Read message from console and publish it to all.
        $console = new TcpConnection(STDIN);
        $console->onMessage = function($console, $message) use ($mqtt){
            $mqtt->publish('workerman', trim($message));
        };
    };
    $mqtt->connect();
};
Worker::runAll();
