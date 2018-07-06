<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Workerman\Mqtt;

use \Workerman\Connection\AsyncTcpConnection;
use \Workerman\Protocols\Mqtt;
use \Workerman\Lib\Timer;

/**
 * Class Client
 * @package Workerman\Mqtt
 */
class Client
{
    /**
     * STATE_INITIAL.
     */
    const STATE_INITIAL = 1;

    /**
     * STATE_CONNECTING
     */
    const STATE_CONNECTING = 2;

    /**
     * STATE_WAITCONACK
     */
    const STATE_WAITCONACK = 3;

    /**
     * STATE_ESTABLISHED
     */
    const STATE_ESTABLISHED = 4;

    /**
     * STATE_DISCONNECT
     */
    const STATE_DISCONNECT = 5;

    /**
     * DEFAULT_CLIENT_ID_PREFIX
     */
    const DEFAULT_CLIENT_ID_PREFIX = 'workerman-mqtt-client';

    /**
     * MAX_TOPIC_LENGTH
     */
    const MAX_TOPIC_LENGTH = 65535;

    /**
     * @var callable
     */
    public $onConnect = null;

    /**
     * @var callable
     */
    public $onReconnect = null;

    /**
     * @var callable
     */
    public $onMessage = null;

    /**
     * @var callable
     */
    public $onClose = null;

    /**
     * @var callable
     */
    public $onError = null;

    /**
     * @var int
     */
    protected $_state = 1;

    /**
     * @var int
     */
    protected $_messageId = 1;

    /**
     * @var string
     */
    protected $_remoteAddress = '';

    /**
     * @var AsyncTcpConnection
     */
    protected $_connection = null;

    /**
     * @var boolean
     */
    protected $_firstConnect = true;

    /**
     * ['topic'=>qos, ...]
     * @var array
     */
    protected $_resubscribeTopics = array();

    /**
     * @var int
     */
    protected $_checkConnectionTimeoutTimer = 0;

    /**
     * @var int
     */
    protected $_pingTimer = 0;

    /**
     * @var bool
     */
    protected $_recvPingResponse = true;

    /**
     * @var bool
     */
    protected $_doNotReconnect = false;

    /**
     * @var array
     */
    protected $_outgoing = array();

    /**
     * @var array
     */
    protected static $_errorCodeStringMap = array(
        1   => 'Connection Refused, unacceptable protocol version',
        2   => 'Connection Refused, identifier rejected',
        3   => 'Connection Refused, Server unavailable',
        4   => 'Connection Refused, bad user name or password',
        5   => 'Connection Refused, not authorized',
        100 => 'Connection closed',
        101 => 'Connection timeout',
        102 => 'Connection fail',
        103 => 'Connection buffer full and close connection',
        140 => 'No connection to broker',
        240 => 'Invalid topic',
        241 => 'Invalid qos',
    );

    /**
     * @var array
     */
    protected $_options = array(
        'clean_session'    => 1, // set to 0 to receive QoS 1 and 2 messages while offline
        'username'         => '', // the username required by your broker
        'password'         => '', // the password required by your broker
        'keepalive'        => 50, // default 50 seconds, set to 0 to disable
        'protocol_name'    => 'MQTT', // protocol name MQTT or MQIsdp
        'protocol_level'   => 4, // protocol level, MQTT is 4 and MQIsdp is 3
        'reconnect_period' => 1, // reconnect period default 1 second, set to 0 to disable
        'connect_timeout'  => 30, // 30 seconds, time to wait before a CONNACK is received
        'resubscribe'      => true, // default true, if connection is broken and reconnects, subscribed topics are automatically subscribed again.
        'bindto'           => '', // bindto option, used to specify the IP address that PHP will use to access the network
        'ssl'              => false, // ssl context, see http://php.net/manual/en/context.ssl.php
        'debug'            => false, // debug
    );

    /**
     * Client constructor.
     * @param $address
     * @param array $options
     */
    public function __construct($address, $options = array())
    {
        class_alias('\Workerman\Mqtt\Protocols\Mqtt', '\Workerman\Protocols\Mqtt');
        $this->setOptions($options);
        $context = array();
        if ($this->_options['bindto']) {
            $context['socket'] = array('bindto' => $this->_options['bindto']);
        }
        if ($this->_options['ssl'] && is_array($this->_options['ssl'])) {
            $context['ssl'] = $this->_options['ssl'];
        }
        if (strpos($address, 'mqtts') === 0) {
            if (empty($this->_options['ssl'])) {
                $this->_options['ssl'] = true;
            }
            $address = str_replace('mqtts', 'mqtt', $address);
        }
        $this->_remoteAddress = $address;
        $this->_connection    = new AsyncTcpConnection($address, $context);
        $this->onReconnect    = array($this, 'onMqttReconnect');
        $this->onMessage      = function(){};
        if ($this->_options['ssl']) {
            $this->_connection->transport = 'ssl';
        }
    }

    /**
     * connect
     */
    public function connect()
    {
        $this->_doNotReconnect           = false;
        $this->_connection->onConnect    = array($this, 'onConnectionConnect');
        $this->_connection->onMessage    = array($this, 'onConnectionMessage');
        $this->_connection->onError      = array($this, 'onConnectionError');
        $this->_connection->onClose      = array($this, 'onConnectionClose');
        $this->_connection->onBufferFull = array($this, 'onConnectionBufferFull');
        $this->_state                    = static::STATE_CONNECTING;
        $this->_connection->connect();
        $this->setConnectionTimeout($this->_options['connect_timeout']);
        if ($this->_options['debug']) {
            echo "-> Try to connect to {$this->_remoteAddress}", PHP_EOL;
        }
    }

    /**
     * subscribe
     *
     * @param $topic
     * @param array $options
     * @param callable $callback
     */
    public function subscribe($topic, $options = array(), $callback = null)
    {
        if ($this->checkDisconnecting($callback)) {
            return;
        }

        if (is_array($topic)) {
            $topics = $topic;
        } else {
            $qos = !is_callable($options) && isset($options['qos']) ? $options['qos'] : 0;
            $topics = array($topic => $qos);
        }

        $args = func_get_args();

        $callback = end($args);

        if (!is_callable($callback)) {
            $callback = null;
        }

        if ($invalid_topic = static::validateTopics($topics)) {
            $this->triggerError(240, $callback);
            return;
        }

        if ($this->_options['resubscribe']) {
            $this->_resubscribeTopics += $topics;
        }

        $package = array(
            'cmd'        => Mqtt::CMD_SUBSCRIBE,
            'topics'     => $topics,
            'message_id' => $this->incrMessageId(),
        );

        if ($this->_options['debug']) {
            echo "-> Send SUBSCRIBE package, topic:".implode(',', array_keys($topics))." message_id:{$package['message_id']}", PHP_EOL;
        }
        $this->sendPackage($package);

        if ($callback) {
            $this->_outgoing[$package['message_id']] = function($exception, $codes = array())use($callback, $topics) {
                if ($exception) {
                    call_user_func($callback, $exception, array());
                    return;
                }
                $granted =  array();
                $topics = array_keys($topics);
                foreach ($topics as $key => $topic) {
                    $granted[$topic] = $codes[$key];
                }
                if ($callback) {
                    call_user_func($callback, null, $granted);
                }
            };
        }
    }

    /**
     * unsubscribe
     *
     * @param $topic
     */
    public function unsubscribe($topic, $callback = null)
    {
        if ($this->checkDisconnecting($callback)) {
            return;
        }
        $topics = is_array($topic) ? $topic : array($topic);
        if ($invalid_topic = static::validateTopics($topics)) {
            $this->triggerError(240);
            return;
        }
        foreach ($topics as $topic) {
            if (isset($this->_resubscribeTopics[$topic])) {
                unset($this->_resubscribeTopics[$topic]);
            }
        }
        $package = array(
            'cmd'        => Mqtt::CMD_UNSUBSCRIBE,
            'topics'     => $topics,
            'message_id' => $this->incrMessageId(),
        );
        if ($callback) {
            $this->_outgoing[$package['message_id']] = $callback;
        }
        if ($this->_options['debug']) {
            echo "-> Send UNSUBSCRIBE package, topic:".implode(',', $topics)." message_id:{$package['message_id']}", PHP_EOL;
        }
        $this->sendPackage($package);
    }

    /**
     * publish
     *
     * @param $topic
     * @param $content
     * @param array $options
     * @param callable $callback
     */
    public function publish($topic, $content, $options = array(), $callback = null)
    {
        if ($this->checkDisconnecting($callback)) {
            return;
        }
        static::isValidTopic($topic);
        $qos    = 0;
        $retain = 0;
        $dup    = 0;
        if (isset($options['qos'])) {
            $qos = $options['qos'];
            if($this->checkInvalidQos($qos, $callback)) {
                return;
            }
        }
        if (!empty($options['retain'])) {
            $retain = 1;
        }
        if (!empty($options['dup'])) {
            $dup = 1;
        }

        $package = array(
            'cmd'     => Mqtt::CMD_PUBLISH,
            'topic'   => $topic,
            'content' => $content,
            'retain'  => $retain,
            'qos'     => $qos,
            'dup'     => $dup,
        );

        if ($qos) {
            $package['message_id'] = $this->incrMessageId();
            if ($callback) {
                $this->_outgoing[$package['message_id']] = $callback;
            }
        }

        if ($this->_options['debug']) {
            $message_id = isset($package['message_id']) ? $package['message_id'] : '';
            echo "-> Send PUBLISH package, topic:$topic content:$content retain:$retain qos:$qos dup:$dup message_id:$message_id", PHP_EOL;
        }

        $this->sendPackage($package);
    }

    /**
     * disconnect
     */
    public function disconnect()
    {
        $this->sendPackage(array('cmd' => Mqtt::CMD_DISCONNECT));
        if ($this->_options['debug']) {
            echo "-> Send DISCONNECT package", PHP_EOL;
        }
        $this->close();
    }

    /**
     * close
     */
    public function close()
    {
        $this->_doNotReconnect = true;
        if ($this->_options['debug']) {
            echo "-> Connection->close() called", PHP_EOL;
        }
        $this->_connection->destroy();
    }

    /**
     * reconnect
     *
     * @param int $after
     */
    public function reconnect($after = 0)
    {
        $this->_doNotReconnect        = false;
        $this->_connection->onConnect = array($this, 'onConnectionConnect');
        $this->_connection->onMessage = array($this, 'onConnectionMessage');
        $this->_connection->onError   = array($this, 'onConnectionError');
        $this->_connection->onClose   = array($this, 'onConnectionClose');
        $this->_connection->reConnect($after);
        $this->setConnectionTimeout($this->_options['connect_timeout'] + $after);
        if ($this->_options['debug']) {
            echo "-- Reconnect after $after seconds", PHP_EOL;
        }
    }

    /**
     * onConnectionConnect
     */
    public function onConnectionConnect()
    {
        if ($this->_doNotReconnect) {
            $this->close();
            return;
        }
        //['cmd'=>1, 'clean_session'=>x, 'will'=>['qos'=>x, 'retain'=>x, 'topic'=>x, 'content'=>x],'username'=>x, 'password'=>x, 'keepalive'=>x, 'protocol_name'=>x, 'protocol_level'=>x, 'client_id' => x]
        $package = array(
            'cmd'            => Mqtt::CMD_CONNECT,
            'clean_session'  => $this->_options['clean_session'],
            'username'       => $this->_options['username'],
            'password'       => $this->_options['password'],
            'keepalive'      => $this->_options['keepalive'],
            'protocol_name'  => $this->_options['protocol_name'],
            'protocol_level' => $this->_options['protocol_level'],
            'client_id'      => $this->_options['client_id'],
        );
        if (isset($this->_options['will'])) {
            $package['will'] = $this->_options['will'];
        }
        $this->_state = static::STATE_WAITCONACK;
        $this->_connection->send($package);
        if ($this->_options['debug']) {
            echo "-- Tcp connection established", PHP_EOL;
            echo "-> Send CONNECT package client_id:{$this->_options['client_id']} username:{$this->_options['username']} password:{$this->_options['password']} clean_session:{$this->_options['clean_session']} protocol_name:{$this->_options['protocol_name']} protocol_level:{$this->_options['protocol_level']}", PHP_EOL;
        }
    }

    /**
     * onMqttReconnect
     */
    public function onMqttReconnect()
    {
        if ($this->_options['clean_session'] && $this->_options['resubscribe'] && $this->_resubscribeTopics) {
            $package = array(
                'cmd'        => Mqtt::CMD_SUBSCRIBE,
                'topics'     => $this->_resubscribeTopics,
                'message_id' => $this->incrMessageId(),
            );
            $this->sendPackage($package);
            if ($this->_options['debug']) {
                echo "-> Send SUBSCRIBE(Resubscribe) package topics:" .
                    implode(',', array_keys($this->_resubscribeTopics))." message_id:{$package['message_id']}", PHP_EOL;
            }
        }
    }

    /**
     * onConnectionMessage
     *
     * @param $connection
     * @param $data
     */
    public function onConnectionMessage($connection, $data)
    {
        $cmd = $data['cmd'];
        switch ($cmd) {
            case Mqtt::CMD_CONNACK:
                $code = $data['code'];
                if ($code != 0) {
                    $message = static::$_errorCodeStringMap[$code];
                    if ($this->_options['debug']) {
                        echo "<- Recv CONNACK package but get error " . $message . PHP_EOL;
                    }
                    $this->triggerError($code);
                    $this->_connection->destroy();
                    return;
                }
                if ($this->_options['debug']) {
                    echo "<- Recv CONNACK package, MQTT connect success", PHP_EOL;
                }
                $this->_state = static::STATE_ESTABLISHED;
                if ($this->_firstConnect) {
                    if ($this->onConnect) {
                        call_user_func($this->onConnect, $this);
                    }
                    $this->_firstConnect = false;
                } else {
                    if ($this->onReconnect) {
                        call_user_func($this->onReconnect, $this);
                    }
                }
                $this->setPingTimer($this->_options['keepalive']);
                $this->cancelConnectionTimeout();
                return;
            //['cmd' => $cmd, 'topic' => $topic, 'content' => $content]
            case Mqtt::CMD_PUBLISH:
                $topic      = $data['topic'];
                $content    = $data['content'];
                $qos        = $data['qos'];
                $message_id = isset($data['message_id']) ? $data['message_id'] : '';
                if ($this->_options['debug']) {
                    echo "<- Recv PUBLISH package, message_id:$message_id qos:$qos topic:$topic content:$content", PHP_EOL;
                }
                call_user_func($this->onMessage, $topic, $content, $this);
                // Connection may be closed in onMessage callback.
                if ($this->_state !== static::STATE_ESTABLISHED) {
                    return;
                }
                switch ($qos) {
                    case 0:
                        break;
                    case 1:
                        if ($this->_options['debug']) {
                            echo "-> Send PUBACK package, message_id:$message_id", PHP_EOL;
                        }
                        $this->sendPackage(array(
                            'cmd'        => Mqtt::CMD_PUBACK,
                            'message_id' => $message_id
                        ));
                        break;
                    case 2:
                        if ($this->_options['debug']) {
                            echo "-> Send PUBREC package, message_id:$message_id", PHP_EOL;
                        }
                        $this->sendPackage(array(
                            'cmd'        => Mqtt::CMD_PUBREC,
                            'message_id' => $message_id
                        ));
                }
                return;
            case Mqtt::CMD_PUBREC:
                $message_id = $data['message_id'];
                if ($this->_options['debug']) {
                    echo "<- Recv PUBREC package, message_id:$message_id", PHP_EOL;
                    echo "-> Send PUBREL package, message_id:$message_id", PHP_EOL;
                }
                $this->sendPackage(array(
                    'cmd'        => Mqtt::CMD_PUBREL,
                    'message_id' => $data['message_id']
                ));
                break;
            case Mqtt::CMD_PUBREL:
                $message_id = $data['message_id'];
                if ($this->_options['debug']) {
                    echo "<- Recv PUBREL package, message_id:$message_id", PHP_EOL;
                    echo "-> Send PUBCOMP package, message_id:$message_id", PHP_EOL;
                }
                $this->sendPackage(array(
                    'cmd'        => Mqtt::CMD_PUBCOMP,
                    'message_id' => $message_id
                ));
                break;
            case Mqtt::CMD_PUBACK:
            case Mqtt::CMD_PUBCOMP:
                $message_id = $data['message_id'];
                if ($this->_options['debug']) {
                    echo "<- Recv ".($cmd == Mqtt::CMD_PUBACK ? 'PUBACK' : 'PUBCOMP') . " package, message_id:$message_id", PHP_EOL;
                }
                if (!empty($this->_outgoing[$message_id])) {
                    if ($this->_options['debug']) {
                        echo "-- Trigger PUB callback for message_id:$message_id", PHP_EOL;
                    }
                    $callback = $this->_outgoing[$message_id];
                    unset($this->_outgoing[$message_id]);
                    call_user_func($callback, null);
                }
                break;
            case Mqtt::CMD_SUBACK:
            case Mqtt::CMD_UNSUBACK:
                $message_id = $data['message_id'];
                if ($this->_options['debug']) {
                    echo "<- Recv ".($cmd == Mqtt::CMD_SUBACK ? 'SUBACK' : 'UNSUBACK') . " package, message_id:$message_id", PHP_EOL;
                }
                $callback = isset($this->_outgoing[$message_id]) ? $this->_outgoing[$message_id] : null;
                unset($this->_outgoing[$message_id]);
                if ($callback) {
                    if ($this->_options['debug']) {
                        echo "-- Trigger ".($cmd == Mqtt::CMD_SUBACK ? 'SUB' : 'UNSUB') . " callback for message_id:$message_id", PHP_EOL;
                    }
                    if ($cmd === Mqtt::CMD_SUBACK) {
                        call_user_func($callback, null, $data['codes']);
                    } else {
                        call_user_func($callback, null);
                    }
                }
                break;
            case Mqtt::CMD_PINGRESP:
                $this->_recvPingResponse = true;
                if ($this->_options['debug']) {
                    echo "<- Recv PINGRESP package", PHP_EOL;
                }
                break;
            default :
                echo "unknow cmd";
        }
    }

    /**
     * onConnectionClose
     */
    public function onConnectionClose()
    {
        if ($this->_options['debug']) {
            echo "-- Connection closed", PHP_EOL;
        }
        $this->cancelPingTimer();
        $this->cancelConnectionTimeout();
        $this->_recvPingResponse = true;
        $this->_state = static::STATE_DISCONNECT;
        if (!$this->_doNotReconnect && $this->_options['reconnect_period'] > 0) {
            $this->reConnect($this->_options['reconnect_period']);
        }

        $this->flushOutgoing();

        if ($this->onClose) {
            call_user_func($this->onClose, $this);
        }
    }

    /**
     * onConnectionError
     *
     * @param $connection
     * @param $code
     */
    public function onConnectionError($connection, $code)
    {
        // Connection error
        if ($code === 1) {
            $this->triggerError(102);
        // Send fail, connection closed
        } else {
            $this->triggerError(100);
        }

    }

    /**
     * onConnectionBufferFull
     */
    public function onConnectionBufferFull()
    {
        if ($this->_options['debug']) {
            echo "-- Connection buffer full and close connection", PHP_EOL;
        }
        $this->triggerError(103);
        $this->_connection->destroy();
    }

    /**
     * incrMessageId
     *
     * @return int
     */
    protected function incrMessageId()
    {
        $message_id = $this->_messageId++;
        if ($message_id >= 65535) {
            $this->_messageId = 1;
        }
        return $message_id;
    }

    /**
     * checkInvalidQos
     *
     * @param $qos
     * @return boolean
     */
    protected function checkInvalidQos($qos, $callback = null)
    {
        if ($qos !== 0 && $qos !== 1 && $qos !== 2) {
            $this->triggerError(241, $callback);
            return true;
        }
        return false;
    }

    /**
     * isValidTopic
     *
     * @param $topic
     * @return boolean
     */
    protected static function isValidTopic($topic)
    {
        if (!static::isString($topic)) {
            return false;
        }
        $topic_length = strlen($topic);
        if ($topic_length > static::MAX_TOPIC_LENGTH) {
            return false;
        }
        return true;
    }

    /**
     * validateTopics
     *
     * @param $topics
     * @return null|string
     */
    protected static function validateTopics($topics)
    {
        if (empty($topics)) {
            return 'array()';
        }
        foreach ($topics as $topic) {
            if(!static::isValidTopic($topic)) {
                return $topic;
            }
        }
        return null;
    }

    /**
     * is string.
     * @param $string
     * @return bool
     */
    protected static function isString($string) {
        return (is_string($string) || is_integer($string)) && strlen($string) > 0;
    }

    /**
     * triggerError
     *
     * @param $exception
     * @param $callback
     */
    protected function triggerError($code, $callback = null)
    {
        $exception = new \Exception(static::$_errorCodeStringMap[$code], $code);
        if ($this->_options['debug']) {
            echo "-- Error: ".$exception->getMessage() . PHP_EOL;
        }
        if (!$callback) {
            $callback = $this->onError ? $this->onError : function($exception){
                echo "Mqtt client: ", $exception->getMessage(), PHP_EOL;
            };
        }
        call_user_func($callback, $exception);
    }

    /**
     * createRandomClientId
     *
     * @return string
     */
    protected function createRandomClientId()
    {
        mt_srand();
        return static::DEFAULT_CLIENT_ID_PREFIX . '-' . mt_rand();
    }

    /**
     * addCheckTimeoutTimer
     */
    protected function setConnectionTimeout($timeout)
    {
        $this->cancelConnectionTimeout();
        $this->_checkConnectionTimeoutTimer = Timer::add($timeout, array($this, 'checkConnectTimeout'), null, false);
    }

    /**
     * cancelConnectionTimeout
     */
    protected function cancelConnectionTimeout()
    {
        if ($this->_checkConnectionTimeoutTimer) {
            Timer::del($this->_checkConnectionTimeoutTimer);
            $this->_checkConnectionTimeoutTimer = 0;
        }
    }

    /**
     * setPingTimer
     */
    protected function setPingTimer($ping_interval)
    {
        $this->cancelPingTimer();
        $connection = $this->_connection;
        $this->_pingTimer = Timer::add($ping_interval, function()use($connection){
            if (!$this->_recvPingResponse) {
                if ($this->_options['debug']) {
                    echo "<- Recv PINGRESP timeout", PHP_EOL;
                    echo "-> Close connection", PHP_EOL;
                }
                $this->_connection->destroy();
                return;
            }
            if ($this->_options['debug']) {
                echo "-> Send PINGREQ package", PHP_EOL;
            }
            $this->_recvPingResponse = false;
            $connection->send(array('cmd' => Mqtt::CMD_PINGREQ));
        });
    }

    /**
     * cancelPingTimer
     */
    protected function cancelPingTimer()
    {
        if ($this->_pingTimer) {
            Timer::del($this->_pingTimer);
            $this->_pingTimer = 0;
        }
    }

    /**
     * checkConnectTimeout
     */
    public function checkConnectTimeout()
    {
        if ($this->_state === static::STATE_CONNECTING || $this->_state === static::STATE_WAITCONACK) {
            $this->triggerError(101);
            $this->_connection->destroy();
        }
    }

    /**
     * checkDisconnecting
     *
     * @param null $callback
     * @return bool
     */
    protected function checkDisconnecting($callback = null)
    {
        if ($this->_state !== static::STATE_ESTABLISHED) {
            $this->triggerError(140, $callback);
            return true;
        }
        return false;
    }

    /**
     * flushOutgoing
     */
    protected function flushOutgoing()
    {
        foreach ($this->_outgoing as $message_id => $callback) {
            $this->triggerError(100, $callback);
        }
        $this->_outgoing = array();
    }

    /**
     * sendPackage
     *
     * @param $package
     */
    protected function sendPackage($package)
    {
        if ($this->checkDisconnecting()) {
            return;
        }
        $this->_connection->send($package);
    }

    /**
     * set options.
     *
     * @param $options
     * @throws \Exception
     */
    protected function setOptions($options)
    {
        if (isset($options['clean_session']) && !$options['clean_session']) {
            $this->_options['clean_session'] = 0;
        }

        if (isset($options['username'])) {
            if (!static::isString($options['username'])) {
                throw new \Exception('Bad username, expected string or integer but ' . gettype($options['username']) . ' provided.');
            }
            $this->_options['username'] = $options['username'];
        }

        if (isset($options['password'])) {
            if (!static::isString($options['password'])) {
                throw new \Exception('Bad password, expected string or integer but ' . gettype($options['password']) . ' provided.');
            }
            $this->_options['password'] = $options['password'];
        }

        if (isset($options['keepalive'])) {
            $keepalive = (int)$options['keepalive'];
            if (!static::isString($keepalive)) {
                throw new \Exception('Bad keepalive, expected integer but ' . gettype($keepalive) . ' provided.');
            }
            if ($keepalive < 0) {
                throw new \Exception('Bad keepalive, expected integer which not less than 0 but ' . $keepalive . ' provided.');
            }
            $this->_options['keepalive'] = $keepalive;
        }

        if (isset($options['protocol_name'])) {
            $protocol_name = $options['protocol_name'];
            if ($protocol_name !== 'MQTT' && $protocol_name !== 'MQIsdp') {
                throw new \Exception('Bad protocol_name of options, expected MQTT or MQIsdp but ' . $protocol_name . ' provided.');
            }
            $this->_options['protocol_name'] = $protocol_name;
        }

        if (isset($options['protocol_level'])) {
            $protocol_level = (int)$options['protocol_level'];
            if ($this->_options['protocol_name'] === 'MQTT' && $protocol_level !== 4) {
                throw new \Exception('Bad protocol_level of options, expected 4 for protocol_name MQTT but ' . $options['protocol_level'] . ' provided.');
            }
            if ($this->_options['protocol_name'] === 'MQIsdp' && $protocol_level !== 3) {
                throw new \Exception('Bad protocol_level of options, expected 3 for protocol_name MQTT but ' . $options['protocol_level'] . ' provided.');
            }
            $this->_options['protocol_level'] = $protocol_level;
        }

        if (isset($options['client_id'])) {
            if (!static::isString($options['client_id'])) {
                throw new \Exception('Bad client_id of options, expected string or integer but ' . gettype($options['client_id']) . ' provided.');
            }
            $this->_options['client_id'] = $options['client_id'];
        } else {
            $this->_options['client_id'] = $this->createRandomClientId();
        }

        if (isset($options['will'])) {
            $will = $options['will'];
            $required = array('qos', 'topic', 'content');
            foreach ($required as $key) {
                if (!isset($will[$key])) {
                    throw new \Exception('Bad will options, $will['.$key.'] missing.');
                }
            }
            if (!static::isString($will['topic'])) {
                throw new \Exception('Bad $will[\'topic\'] of options, expected string or integer but ' . gettype($will['topic']) . ' provided.');
            }
            if (!static::isString($will['content'])) {
                throw new \Exception('Bad $will[\'content\'] of options, expected string or integer but ' . gettype($will['content']) . ' provided.');
            }
            if ($this->checkInvalidQos($will['qos'])) {
                throw new \Exception('Bad will qos:' . var_export($will['qos'], true));
            }
            $this->_options['will'] = $options['will'];
        }

        if (isset($options['reconnect_period'])) {
            $reconnect_period = (int)$options['reconnect_period'];
            if (!static::isString($reconnect_period)) {
                throw new \Exception('Bad reconnect_period of options, expected integer but ' . gettype($options['reconnect_period']) . ' provided.');
            }
            if ($reconnect_period < 0) {
                throw new \Exception('Bad reconnect_period, expected integer which not less than 0 but ' . $options['reconnect_period'] . ' provided.');
            }
            $this->_options['reconnect_period'] = $reconnect_period;
        }

        if (isset($options['connect_timeout'])) {
            $connect_timeout = (int)$options['connect_timeout'];
            if (!static::isString($connect_timeout)) {
                throw new \Exception('Bad connect_timeout of options, expected integer but ' . gettype($options['connect_timeout']) . ' provided.');
            }
            if ($connect_timeout <= 0) {
                throw new \Exception('Bad connect_timeout, expected integer which greater than 0 but ' . $options['connect_timeout'] . ' provided.');
            }
            $this->_options['connect_timeout'] = $connect_timeout;
        }

        if (isset($options['resubscribe']) && !$options['resubscribe']) {
            $this->_options['resubscribe'] = false;
        }

        if (!empty($options['bindto'])) {
            $this->_options['bindto'] = $options['bindto'];
        }

        if (isset($options['ssl'])) {
            $this->_options['ssl'] = $options['ssl'];
        }

        if (isset($options['debug'])) {
            $this->_options['debug'] = !empty($options['debug']);
        }
    }
}
