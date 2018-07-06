<?php
require __DIR__ . '/../vendor/autoload.php';
use Workerman\Worker;
use Workerman\Lib\Timer;

$worker = new Worker();
$worker->onWorkerStart = function(){
    $mqtt = new Workerman\Mqtt\Client('mqtts://test.mosquitto.org:8883', array(
        'ssl' => array(
            'local_cert'  => __DIR__ . '/ssl/mosquitto.org.crt',
            'local_pk'    => __DIR__ . '/ssl/mosquitto.org.key',
            'verify_peer' => false,
        ),
        'debug' => true
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
