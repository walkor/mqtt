<?php
require __DIR__ . '/../../vendor/autoload.php';
use Workerman\Worker;
use Workerman\Timer;

$worker = new Worker();
$worker->onWorkerStart = function(){
    $mqtt = new Workerman\Mqtt\Client('ws://host.docker.internal:15675', array(
        'debug' => true,
        "username"=>"guest",
        "password"=>"guest",
        'uri' => '/ws', # mqtt over websocket的uri，这里测试使用的是rabbitmq-mqtt所以是/ws，通常是/mqtt
    ));
    $mqtt->onConnect = function(Workerman\Mqtt\Client $mqtt) {
        // publish message every 2 seconds.
        Timer::add(2, function() use ($mqtt) {
            $mqtt->publish('mqtt_exchange',
                '{"time":' . 1223 . '}',
                [
                    'qos' =>0,
                    'retain' =>0,
                    'dup' => 0
                ],
                null,
                [
                    'topic_alias' => 1,
                    'message_expiry_interval' => 12,
                    'correlation_data' => 'your_correlation_data',
                    'response_topic' => 'your_response_topic',
                ]
            );
        });
    };
    $mqtt->connect();
};
Worker::runAll();
