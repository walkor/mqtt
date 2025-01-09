<?php
require __DIR__ . '/../../vendor/autoload.php';
use Workerman\Worker;
use Workerman\Timer;

$worker = new Worker();
$worker->onWorkerStart = function(){
    $mqtt = new Workerman\Mqtt\Client('ws://host.docker.internal:15675', array(
        'debug' => true,
        "username"=>"guest", "password"=>"guest",
        'properties' =>[
            'session_expiry_interval' => 60,
            'receive_maximum' => 65535,
            'topic_alias_maximum' => 65535,
        ],
        'protocol_level' => 5,
        'uri' => '/ws'

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
