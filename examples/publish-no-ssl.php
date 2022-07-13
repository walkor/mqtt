<?php
require __DIR__ . '/../vendor/autoload.php';
use Workerman\Worker;
use Workerman\Timer;

$worker = new Worker();
$worker->onWorkerStart = function(){
    $mqtt = new Workerman\Mqtt\Client('mqtt://broker.emqx.io:1883', array(
        'debug' => true,
        "username"=>"rw",
        "password"=>"readwrite"
    ));
    $mqtt->onConnect = function($mqtt) {
        // publish message every 2 seconds.
        Timer::add(2, function() use ($mqtt) {
            $mqtt->publish('workerman', 'hello workerman mqtt');
        });
    };
    $mqtt->connect();
};
Worker::runAll();
