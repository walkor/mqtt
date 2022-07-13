<?php
require __DIR__ . '/../vendor/autoload.php';
use Workerman\Worker;

$worker = new Worker();
$worker->onWorkerStart = function(){
    $mqtt = new Workerman\Mqtt\Client('mqtt://broker.emqx.io:1883', array(
        'debug' => true,
        "username"=>"rw", "password"=>"readwrite"
    ));
    $mqtt->onConnect = function($mqtt) {
        $mqtt->subscribe('workerman');
    };
    $mqtt->onMessage = function($topic, $content){
        echo "topic:$topic content:$content\n";
    };
    $mqtt->connect();
};
Worker::runAll();
