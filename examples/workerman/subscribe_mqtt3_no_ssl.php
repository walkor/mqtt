<?php
require __DIR__ . '/../../vendor/autoload.php';
use Workerman\Worker;

$worker = new Worker();
$worker->onWorkerStart = function(){
    $will = [
        'topic' => 'mqtt_exchange',
        'qos' => 1,
        'retain' => 0,
        'content' => 'byebye'
    ];
    $mqtt = new Workerman\Mqtt\Client('mqtt://host.docker.internal:1883', array(
        'debug' => true,
        "username"=>"guest", "password"=>"guest",
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
