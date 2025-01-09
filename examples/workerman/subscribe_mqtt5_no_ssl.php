<?php
require __DIR__ . '/../../vendor/autoload.php';
use Workerman\Worker;

$worker = new Worker();
$worker->onWorkerStart = function(){
    $will = [
        'topic' => 'mqtt_exchange',
        'qos' => 1,
        'retain' => 0,
        'content' => 'byebye',
        'properties' => [
            'will_delay_interval' => 60,
            'message_expiry_interval' => 60,
            'content_type' => 'test',
            'payload_format_indicator' => true, // false 0 1
        ],
    ];
    $mqtt = new Workerman\Mqtt\Client('mqtt://host.docker.internal:1883', array(
        'debug' => true,
        "username"=>"guest", "password"=>"guest",
        'properties' =>[
            'session_expiry_interval' => 60,
            'receive_maximum' => 65535,
            'topic_alias_maximum' => 65535,
        ],
        'protocol_level' => 5,
        'will' => $will
    ));
    $mqtt->onConnect = function(\Workerman\Mqtt\Client $mqtt) {

        $mqtt->subscribe('mqtt_exchange', [
            'qos' => 0,
            'no_local' => false,
            'retain_as_published' => true,
            'retain_handling' => 2,
        ]);
    };
    $mqtt->onMessage = function($topic, $content){
        echo "topic:$topic content:$content\n";
    };
    $mqtt->connect();
};
Worker::runAll();
