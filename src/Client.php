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

use InvalidArgumentException;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\ConnectionInterface;
use Workerman\Mqtt\Consts\MQTTConst;
use Workerman\Mqtt\Consts\ReasonCodeConst;
use Workerman\Mqtt\Protocols\Mqtt;
use Workerman\Mqtt\Protocols\Mqtt5;
use Workerman\Mqtt\Protocols\ProtocolInterface;
use Workerman\Mqtt\Protocols\Ws;
use Workerman\Timer;

/**
 * Class Client
 */
class Client
{
    /**
     * STATE_INITIAL.
     */
    public const STATE_INITIAL = 1;

    /**
     * STATE_CONNECTING
     */
    public const STATE_CONNECTING = 2;

    /**
     * STATE_WAITCONACK
     */
    public const STATE_WAITCONACK = 3;

    /**
     * STATE_ESTABLISHED
     */
    public const STATE_ESTABLISHED = 4;

    /**
     * STATE_DISCONNECT
     */
    public const STATE_DISCONNECT = 5;

    /**
     * DEFAULT_CLIENT_ID_PREFIX
     */
    public const DEFAULT_CLIENT_ID_PREFIX = 'workerman-mqtt-client';

    /**
     * MAX_TOPIC_LENGTH
     */
    public const MAX_TOPIC_LENGTH = 65535;

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
    protected int $_state = 1;

    /**
     * @var int
     */
    protected int $_messageId = 1;


    /**
     * @var AsyncTcpConnection|null
     */
    protected ?AsyncTcpConnection $_connection = null;

    /**
     * @var bool
     */
    protected bool $_firstConnect = true;

    /**
     * ['topic'=>qos, ...]
     * @var array
     */
    protected array $_resubscribeTopics = [];

    /**
     * @var array
     */
    protected array $_resubscribeProperties = [];

    /**
     * @var int
     */
    protected int $_checkConnectionTimeoutTimer = 0;

    /**
     * @var int
     */
    protected int $_pingTimer = 0;

    /**
     * @var bool
     */
    protected bool $_recvPingResponse = true;

    /**
     * @var bool
     */
    protected bool $_doNotReconnect = false;

    /**
     * @var array
     */
    protected array $_outgoing = [];

    /**
     * @var class-string
     */
    protected string $_protocol = Mqtt::class;

    /**
     * @var array
     */
    protected array $_options = [
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
        'properties'       => [],  // properties, MQTT5 need
        'uri'              => '/mqtt', // mqtt over websocket default uri
    ];

    /**
     * @var array
     */
    protected static array $_errorCodeStringMap = [
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
    ];

    /**
     * @var array|string[]
     */
    protected static array $_protocolMap = [
        'ws'        => Ws::class,
        'wss'       => Ws::class,
        'mqtt'      => Mqtt::class,
        'mqtts'     => Mqtt::class,
    ];

    /**
     * 注册协议
     *
     * @param string $schema
     * @param string $protocolClassname
     * @return void
     */
    public static function registerProtocol(string $schema, string $protocolClassname): void
    {
        if (!is_a($protocolClassname, ProtocolInterface::class, true)) {
            throw new InvalidArgumentException("$protocolClassname must implement ProtocolInterface");
        }
        static::$_protocolMap[$schema] = $protocolClassname;
    }

    /**
     * @return AsyncTcpConnection|null
     */
    public function getConnection(): ?AsyncTcpConnection
    {
        return $this->_connection;
    }

    /**
     * @param string $address
     * @param array $options
     * @throws \Exception
     */
    public function __construct(string $address, array $options = [])
    {
        $context = [];
        $this->setOptions($options);
        // 解析协议
        $addressInfo = parse_url($address);
        $schema = $addressInfo['scheme'] ?? '';
        /** @var ProtocolInterface $protocol */
        $this->_protocol = static::$_protocolMap[$schema] ?? Mqtt::class;
        // 协议初始化
        $this->_protocol = $this->_protocol::init($address, $this->_options, $context);
        // init connection
        $this->_connection = new AsyncTcpConnection($address, $context);
        $this->_connection->transport = $this->_options['ssl'] ? 'ssl': $this->_connection->transport;
        $this->onReconnect = [$this, 'onMqttReconnect'];
        $this->onMessage = function () {};
        // MQTT over websocket
        $this->_connection->websocketType = Ws::BINARY_TYPE_ARRAYBUFFER;
        $this->_connection->websocketClientProtocol = 'mqtt';
        $this->_connection->websocketClientDataProtocolClass = $this->_options['protocol_level'] === 5 ? Mqtt5::class : Mqtt::class;
        // 协议类标识
    }

    /**
     * connect
     *
     * @return void
     */
    public function connect(): void
    {
        $this->_doNotReconnect              = false;
        $this->_connection->onConnect       = [$this, 'onConnectionConnect'];
        $this->_connection->onClose         = [$this, 'onConnectionClose'];
        $this->_connection->onError         = [$this, 'onConnectionError'];
        $this->_connection->onMessage       = [$this, 'onConnectionMessage'];
        $this->_connection->onBufferFull    = [$this, 'onConnectionBufferFull'];
        $this->_state                       = static::STATE_CONNECTING;
        $this->_connection->connect();
        $this->setConnectionTimeout($this->_options['connect_timeout']);
        $this->debugDump("Try to connect to {$this->_connection->getRemoteAddress()}{$this->_connection->getRemoteURI()}, protocol: {$this->_protocol}", '->');
    }

    /**
     * subscribe
     *
     * @param array<string, string>|string $topic
     * @param array $options
     * @param callable|null $callback
     * @param array $properties
     * @return void
     */
    public function subscribe(array|string $topic, array $options = [], ?callable $callback = null, array $properties = []): void
    {
        if ($this->checkDisconnecting($callback)) {
            return;
        }

        if (is_array($topic)) {
            $topics = $topic;
        } else {
            $qos = !is_callable($options) && isset($options['qos']) ? $options['qos'] : 0;
            if ((int) $this->_options['protocol_level'] === 5) {
                $no_local = !is_callable($options) && isset($options['no_local']) ? $options['no_local'] : null;
                $retain_as_published = !is_callable($options) && isset($options['retain_as_published']) ? $options['retain_as_published'] : null;
                $retain_handling = !is_callable($options) && isset($options['retain_handling']) ? $options['retain_handling'] : null;
                $topics = [$topic => [
                    'qos'                 => $qos,
                    'no_local'            => $no_local,
                    'retain_as_published' => $retain_as_published,
                    'retain_handling'     => $retain_handling,
                ]];
            } else {
                $topics = [$topic => $qos];
            }
        }
        if (static::validateTopics($topics)) {
            $this->triggerError(240, $callback);

            return;
        }
        if ($this->_options['resubscribe']) {
            $this->_resubscribeTopics += $topics;
            $this->_resubscribeProperties += $properties;
        }
        $package = [
            'cmd'        => MQTTConst::CMD_SUBSCRIBE,
            'topics'     => $topics,
            'message_id' => $this->incrMessageId(),
            'properties' => $properties,
        ];
        $this->debugDump(
            'Send SUBSCRIBE package, topic:' . implode(',', array_keys($topics)) .
            " message_id:{$package['message_id']}" . ' properties:' . json_encode($properties),
            '->'
        );
        $this->sendPackage($package);

        if ($callback) {
            $this->_outgoing[$package['message_id']] = function ($exception, $codes = []) use ($callback, $topics) {
                if ($exception) {
                    call_user_func($callback, $exception, []);

                    return;
                }
                $granted = [];
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
     * @param array|string $topic
     * @param callable|null $callback
     * @param array $properties
     */
    public function unsubscribe(array|string $topic, ?callable $callback = null, array $properties = []): void
    {
        if ($this->checkDisconnecting($callback)) {
            return;
        }
        $topics = is_array($topic) ? $topic : [$topic];
        if (static::validateTopics($topics)) {
            $this->triggerError(240);

            return;
        }
        foreach ($topics as $_topic) {
            if (isset($this->_resubscribeTopics[$_topic])) {
                unset($this->_resubscribeTopics[$_topic]);
            }
        }
        $this->_resubscribeProperties = [];

        $package = [
            'cmd'        => MQTTConst::CMD_UNSUBSCRIBE,
            'topics'     => $topics,
            'message_id' => $this->incrMessageId(),
            'properties' => $properties,
        ];
        if ($callback) {
            $this->_outgoing[$package['message_id']] = $callback;
        }
        $this->debugDump(
            'Send UNSUBSCRIBE package, topic:' . implode(',', $topics) .
            " message_id:{$package['message_id']}" . ' properties:' . json_encode($properties),
            '->'
        );
        $this->sendPackage($package);
    }

    /**
     * publish
     *
     * @param $topic
     * @param $content
     * @param array $options
     * @param callable $callback
     * @param array $properties
     */
    public function publish($topic, $content, $options = [], $callback = null, $properties = []): void
    {
        if ($this->checkDisconnecting($callback)) {

            return;
        }
        if ((int) $this->_options['protocol_level'] === 5) {
            if (empty($topic)) {
                if (empty($properties['topic_alias'])) {
                    throw new \RuntimeException('Topic cannot be empty or need to set topic_alias');
                }
            }
        } else {
            static::isValidTopic($topic);
        }
        $qos = 0;
        $retain = 0;
        $dup = 0;
        if (isset($options['qos'])) {
            $qos = $options['qos'];
            if ($this->checkInvalidQos($qos, $callback)) {

                return;
            }
        }
        if (!empty($options['retain'])) {
            $retain = 1;
        }
        if (!empty($options['dup'])) {
            $dup = 1;
        }
        $package = [
            'cmd'        => MQTTConst::CMD_PUBLISH,
            'topic'      => $topic,
            'content'    => $content,
            'retain'     => $retain,
            'qos'        => $qos,
            'dup'        => $dup,
            'properties' => $properties,
        ];
        if ($qos) {
            $package['message_id'] = $this->incrMessageId();
            if ($callback) {
                $this->_outgoing[$package['message_id']] = $callback;
            }
        }
        $this->debugDump(
            "Send PUBLISH package, topic:$topic content:$content retain:$retain qos:$qos dup:$dup message_id:" .
            ($package['message_id'] ?? '') . ' properties:' . json_encode($properties),
            '->'
        );
        $this->sendPackage($package);
    }

    /**
     * disconnect
     *
     * @param int $code
     * @param array $properties
     * @return void
     */
    public function disconnect(int $code = ReasonCodeConst::NORMAL_DISCONNECTION, array $properties = []): void
    {
        $this->sendPackage(['cmd' => MQTTConst::CMD_DISCONNECT, 'code' => $code, 'properties' => $properties]);
        if ($this->_options['debug']) {
            echo '-> Send DISCONNECT package', PHP_EOL;
        }
        $this->close();
    }

    /**
     * auth
     *
     * @param int $code
     * @param array $properties
     * @return void
     */
    public function auth(int $code = ReasonCodeConst::SUCCESS, array $properties = []): void
    {
        $this->sendPackage(['cmd' => MQTTConst::CMD_AUTH, 'code' => $code, 'properties' => $properties]);
    }

    /**
     * close
     */
    public function close(): void
    {
        $this->_doNotReconnect = true;
        $this->debugDump('Connection->close() called', '->');
        $this->_connection->destroy();
    }

    /**
     * reconnect
     *
     * @param int $after
     */
    public function reconnect(int $after = 0): void
    {
        $this->_doNotReconnect = false;
        $this->_connection->onConnect = [$this, 'onConnectionConnect'];
        $this->_connection->onMessage = [$this, 'onConnectionMessage'];
        $this->_connection->onError = [$this, 'onConnectionError'];
        $this->_connection->onClose = [$this, 'onConnectionClose'];
        $this->_connection->reConnect($after);
        $this->setConnectionTimeout($this->_options['connect_timeout'] + $after);
        $this->debugDump("Reconnect after $after seconds", '--');
    }

    /**
     * onConnectionConnect
     * @return void
     */
    public function onConnectionConnect(): void
    {
        if ($this->_doNotReconnect) {
            $this->close();

            return;
        }
        //['cmd'=>1, 'clean_session'=>x, 'will'=>['qos'=>x, 'retain'=>x, 'topic'=>x, 'content'=>x],'username'=>x, 'password'=>x, 'keepalive'=>x, 'protocol_name'=>x, 'protocol_level'=>x, 'client_id' => x]
        $package = [
            'cmd'            => MQTTConst::CMD_CONNECT,
            'clean_session'  => $this->_options['clean_session'],
            'username'       => $this->_options['username'],
            'password'       => $this->_options['password'],
            'keepalive'      => $this->_options['keepalive'],
            'protocol_name'  => $this->_options['protocol_name'],
            'protocol_level' => $this->_options['protocol_level'],
            'client_id'      => $this->_options['client_id'],
            'properties'     => $this->_options['properties'], // MQTT5 中所需要的属性
        ];
        if (isset($this->_options['will'])) {
            $package['will'] = $this->_options['will'];
        }
        $this->_state = static::STATE_WAITCONACK;
        $this->sendPackage($package, false);
        $this->debugDump('Tcp connection established', '--');
        $this->debugDump(
            "Send CONNECT package client_id:{$this->_options['client_id']} username:{$this->_options['username']} password:{$this->_options['password']} clean_session:{$this->_options['clean_session']} protocol_name:{$this->_options['protocol_name']} protocol_level:{$this->_options['protocol_level']}",
            '->'
        );
    }

    /**
     * onMqttReconnect
     *
     * @return void
     */
    public function onMqttReconnect(): void
    {
        if ($this->_options['clean_session'] && $this->_options['resubscribe'] && $this->_resubscribeTopics) {
            $package = [
                'cmd'        => MQTTConst::CMD_SUBSCRIBE,
                'topics'     => $this->_resubscribeTopics,
                'message_id' => $this->incrMessageId(),
                'properties' => $this->_resubscribeProperties ?? [],
            ];
            $this->sendPackage($package);
            $this->debugDump(
                'Send SUBSCRIBE(Resubscribe) package topics:' .
                implode(',', array_keys($this->_resubscribeTopics)) . " message_id:{$package['message_id']}",
                '->'
            );
        }
    }

    /**
     * onConnectionMessage
     *
     * @param ConnectionInterface $connection
     * @param $data
     */
    public function onConnectionMessage(ConnectionInterface $connection, $data): void
    {
        $data = $this->_protocol::unpack($data, $connection);
        $cmd = $data['cmd'];
        switch ($cmd) {
            case MQTTConst::CMD_CONNACK:
                $code = (int) $data['code'];
                if ($code !== 0) {
                    $message = $this->getErrorCodeString($code);
                    $this->debugDump("Recv CONNACK package but get error $message", '<-');
                    $this->triggerError($code);
                    $this->_connection->destroy();

                    return;
                }
                $this->debugDump("Recv CONNACK package, MQTT connect success", '<-');
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
                if ($this->_options['keepalive']) {
                    $this->setPingTimer($this->_options['keepalive']);
                }
                $this->cancelConnectionTimeout();

                return;
                //['cmd' => $cmd, 'topic' => $topic, 'content' => $content]
            case MQTTConst::CMD_PUBLISH:
                $topic = $data['topic'];
                $content = $data['content'];
                $qos = $data['qos'];
                $message_id = $data['message_id'] ?? '';
                $properties = $data['properties'] ?? [];
                $this->debugDump(
                    "Recv PUBLISH package, message_id:$message_id qos:$qos topic:$topic content:$content properties:" . json_encode($properties),
                    '<-'
                );
                call_user_func($this->onMessage, $topic, $content, $this, $properties);
                // Connection may be closed in onMessage callback.
                if ($this->_state !== static::STATE_ESTABLISHED) {
                    return;
                }
                $extra_package = [];
                if ((int) $this->_options['protocol_level'] === 5) {
                    $extra_package = ['properties' => $properties];
                }
                switch ($qos) {
                    case 0:
                        break;
                    case 1:
                        $this->debugDump("Send PUBACK package, message_id: $message_id", '->');
                        $this->sendPackage([
                            'cmd'        => MQTTConst::CMD_PUBACK,
                            'message_id' => $message_id,
                        ] + $extra_package);
                        break;
                    case 2:
                        $this->debugDump("Send PUBREC package, message_id: $message_id", '->');
                        $this->sendPackage([
                            'cmd'        => MQTTConst::CMD_PUBREC,
                            'message_id' => $message_id,
                        ] + $extra_package);
                }

                return;
            case MQTTConst::CMD_PUBREC:
                $message_id = $data['message_id'];
                $properties = $data['properties'] ?? [];
                $this->debugDump("Recv PUBREC package, message_id:$message_id", '<-');
                $this->debugDump("Send PUBREL package, message_id: $message_id", '->');
                $extra_package = [];
                if ((int) $this->_options['protocol_level'] === 5) {
                    $extra_package = ['properties' => $properties];
                }
                $this->sendPackage([
                    'cmd'        => MQTTConst::CMD_PUBREL,
                    'message_id' => $data['message_id'],
                ] + $extra_package);
                break;
            case MQTTConst::CMD_PUBREL:
                $message_id = $data['message_id'];
                $properties = $data['properties'] ?? [];
                $this->debugDump("Recv PUBREL package, message_id: $message_id", '<-');
                $this->debugDump("Send PUBCOMP package, message_id: $message_id", '->');
                $extra_package = [];
                if ((int)$this->_options['protocol_level'] === 5) {
                    $extra_package = ['properties' => $properties];
                }
                $this->sendPackage([
                    'cmd'        => MQTTConst::CMD_PUBCOMP,
                    'message_id' => $message_id,
                ] + $extra_package);
                break;
            case MQTTConst::CMD_PUBACK:
            case MQTTConst::CMD_PUBCOMP:
                $message_id = $data['message_id'];
                $this->debugDump("Recv " . ($cmd === MQTTConst::CMD_PUBACK ? 'PUBACK' : 'PUBCOMP') . " package, message_id: $message_id", '<-');
                if (!empty($this->_outgoing[$message_id])) {
                    $this->debugDump("Trigger PUB callback for message_id: $message_id");
                    $callback = $this->_outgoing[$message_id];
                    unset($this->_outgoing[$message_id]);
                    call_user_func($callback, null);
                }
                break;
            case MQTTConst::CMD_SUBACK:
            case MQTTConst::CMD_UNSUBACK:
                $message_id = $data['message_id'];
                $this->debugDump('Recv ' . ($cmd === MQTTConst::CMD_SUBACK ? 'SUBACK' : 'UNSUBACK') . " package, message_id: $message_id", '<-');
                $callback = $this->_outgoing[$message_id] ?? null;
                unset($this->_outgoing[$message_id]);
                if ($callback) {
                    $this->debugDump('Trigger ' . ($cmd === MQTTConst::CMD_SUBACK ? 'SUB' : 'UNSUB') . " callback for message_id: $message_id", '--');
                    if ($cmd === MQTTConst::CMD_SUBACK) {
                        call_user_func($callback, null, $data['codes']);
                    } else {
                        call_user_func($callback, null);
                    }
                }
                break;
            case MQTTConst::CMD_PINGRESP:
                $this->_recvPingResponse = true;
                $this->debugDump('Recv PINGRESP package', '<-');
                break;
            default:
                $this->debugDump('Recv unknow package, cmd: ' . $cmd, '<-');
                echo 'unknow cmd';
        }
    }

    /**
     * onConnectionClose
     *
     * @return void
     */
    public function onConnectionClose(): void
    {
        $this->debugDump('Connection closed', '--');
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
     * @param ConnectionInterface $connection
     * @param $code
     * @return void
     */
    public function onConnectionError(ConnectionInterface $connection, $code): void
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
     *
     * @return void
     */
    public function onConnectionBufferFull(): void
    {
        $this->debugDump('Connection buffer full and close connection', '--');
        $this->triggerError(103);
        $this->_connection->destroy();
    }

    /**
     * debug echo
     *
     * @param string $message
     * @param string $tag
     * @return void
     */
    protected function debugDump(string $message, string $tag = '--'): void
    {
        if ($this->_options['debug'] ?? false) {
            echo "$tag $message\n";
        }
    }

    /**
     * sendPackage
     *
     * @param mixed $package
     * @param bool $checkDisconnecting
     */
    protected function sendPackage(mixed $package, bool $checkDisconnecting = true): void
    {
        if ($checkDisconnecting and $this->checkDisconnecting()) {
            return;
        }
        $this->_connection->send($this->_protocol::pack($package, $this->_connection));
    }

    /**
     * incrMessageId
     *
     * @return int
     */
    protected function incrMessageId(): int
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
     * @param callable|null $callback
     * @return bool
     */
    protected function checkInvalidQos($qos, ?callable $callback = null): bool
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
     * @return bool
     */
    protected static function isValidTopic($topic): bool
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
     * @return string|null
     */
    protected static function validateTopics($topics): string|null
    {
        if (empty($topics)) {
            return 'array()';
        }
        foreach ($topics as $topic => $qos) {
            if (!static::isValidTopic($topic)) {
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
    protected static function isString($string): bool
    {
        return (is_string($string) || is_integer($string)) && strlen($string) > 0;
    }

    /**
     * triggerError
     *
     * @param $code
     * @param callable|null $callback
     */
    protected function triggerError($code, ?callable $callback = null): void
    {
        $exception = new \Exception($this->getErrorCodeString($code), $code);
        $this->debugDump('Error: ' . $exception->getMessage(), '--');

        if (!$callback) {
            $callback = $this->onError ?: function ($exception) {
                echo 'Mqtt client: ', $exception->getMessage(), PHP_EOL;
            };
        }
        call_user_func($callback, $exception);
    }

    /**
     * @param int $code
     * @return mixed|string
     */
    protected function getErrorCodeString(int $code)
    {
        return  static::$_errorCodeStringMap[$code] ?? ReasonCodeConst::getReason($code);
    }

    /**
     * createRandomClientId
     *
     * @return string
     */
    protected function createRandomClientId(): string
    {
        mt_srand();

        return static::DEFAULT_CLIENT_ID_PREFIX . '-' . mt_rand();
    }

    /**
     * addCheckTimeoutTimer
     *
     * @param $timeout
     * @return void
     */
    protected function setConnectionTimeout($timeout): void
    {
        $this->cancelConnectionTimeout();
        $this->_checkConnectionTimeoutTimer = Timer::add($timeout, [$this, 'checkConnectTimeout'], null, false);
    }

    /**
     * cancelConnectionTimeout
     *
     * @return void
     */
    protected function cancelConnectionTimeout(): void
    {
        if ($this->_checkConnectionTimeoutTimer) {
            Timer::del($this->_checkConnectionTimeoutTimer);
            $this->_checkConnectionTimeoutTimer = 0;
        }
    }

    /**
     * setPingTimer
     *
     * @param float|int $pingInterval
     * @return void
     */
    protected function setPingTimer(float|int $pingInterval): void
    {
        $this->cancelPingTimer();
        $this->_pingTimer = Timer::add($pingInterval, function () {
            if (!$this->_recvPingResponse) {
                $this->debugDump('Recv PINGRESP timeout', '<-');
                $this->debugDump('Close connection', '->');
                $this->_connection->destroy();

                return;
            }
            $this->debugDump('Send PINGREQ package', '->');
            $this->_recvPingResponse = false;
            $this->sendPackage(['cmd' => MQTTConst::CMD_PINGREQ], false);
        });
    }

    /**
     * cancelPingTimer
     *
     * @return void
     */
    protected function cancelPingTimer(): void
    {
        if ($this->_pingTimer) {
            Timer::del($this->_pingTimer);
            $this->_pingTimer = 0;
        }
    }

    /**
     * checkConnectTimeout
     *
     * @return void
     */
    public function checkConnectTimeout(): void
    {
        if ($this->_state === static::STATE_CONNECTING || $this->_state === static::STATE_WAITCONACK) {
            $this->triggerError(101);
            $this->_connection->destroy();
        }
    }

    /**
     * checkDisconnecting
     *
     * @param callable|null $callback
     * @return bool
     */
    protected function checkDisconnecting(?callable $callback = null): bool
    {
        if ($this->_state !== static::STATE_ESTABLISHED) {
            $this->triggerError(140, $callback);

            return true;
        }

        return false;
    }

    /**
     * flushOutgoing
     *
     * @return void
     */
    protected function flushOutgoing(): void
    {
        foreach ($this->_outgoing as $message_id => $callback) {
            $this->triggerError(100, $callback);
        }
        $this->_outgoing = [];
    }

    /**
     * set options.
     *
     * @param array $options
     */
    protected function setOptions(array $options): void
    {
        if (isset($options['clean_session']) && !$options['clean_session']) {
            $this->_options['clean_session'] = 0;
        }

        if (isset($options['uri'])) {
            $this->_options['uri'] = $options['uri'];
        }

        if (isset($options['username'])) {
            if (!static::isString($options['username'])) {
                throw new InvalidArgumentException('Bad username, expected string or integer but ' . gettype($options['username']) . ' provided.');
            }
            $this->_options['username'] = $options['username'];
        }

        if (isset($options['password'])) {
            if (!static::isString($options['password'])) {
                throw new InvalidArgumentException('Bad password, expected string or integer but ' . gettype($options['password']) . ' provided.');
            }
            $this->_options['password'] = $options['password'];
        }

        if (isset($options['keepalive'])) {
            $keepalive = (int) $options['keepalive'];
            if (!static::isString($keepalive)) {
                throw new InvalidArgumentException('Bad keepalive, expected integer but ' . gettype($keepalive) . ' provided.');
            }
            if ($keepalive < 0) {
                throw new InvalidArgumentException('Bad keepalive, expected integer which not less than 0 but ' . $keepalive . ' provided.');
            }
            $this->_options['keepalive'] = $keepalive;
        }

        if (isset($options['protocol_name'])) {
            $protocol_name = $options['protocol_name'];
            if ($protocol_name !== 'MQTT' && $protocol_name !== 'MQIsdp') {
                throw new InvalidArgumentException('Bad protocol_name of options, expected MQTT or MQIsdp but ' . $protocol_name . ' provided.');
            }
            $this->_options['protocol_name'] = $protocol_name;
        }

        if (isset($options['protocol_level'])) {
            $protocol_level = (int) $options['protocol_level'];
            if ($this->_options['protocol_name'] === 'MQTT' && !in_array($protocol_level, [4, 5])) {
                throw new InvalidArgumentException('Bad protocol_level of options, expected (4 or 5) for protocol_name MQTT but ' . $options['protocol_level'] . ' provided.');
            }
            if ($this->_options['protocol_name'] === 'MQIsdp' && $protocol_level !== 3) {
                throw new InvalidArgumentException('Bad protocol_level of options, expected 3 for protocol_name MQTT but ' . $options['protocol_level'] . ' provided.');
            }
            $this->_options['protocol_level'] = $protocol_level;
        }

        if (isset($options['client_id'])) {
            if (!static::isString($options['client_id'])) {
                throw new InvalidArgumentException('Bad client_id of options, expected string or integer but ' . gettype($options['client_id']) . ' provided.');
            }
            $this->_options['client_id'] = $options['client_id'];
        } else {
            $this->_options['client_id'] = $this->createRandomClientId();
        }

        // 遗嘱消息，当客户端断线后Broker会自动发送遗嘱消息给其它客户端
        if (isset($options['will'])) {
            $will = $options['will'];
            $required = ['qos', 'topic', 'content'];
            foreach ($required as $key) {
                if (!isset($will[$key])) {
                    throw new InvalidArgumentException('Bad will options, $will[' . $key . '] missing.');
                }
            }
            if (!static::isString($will['topic'])) {
                throw new InvalidArgumentException('Bad $will[\'topic\'] of options, expected string or integer but ' . gettype($will['topic']) . ' provided.');
            }
            if (!static::isString($will['content'])) {
                throw new InvalidArgumentException('Bad $will[\'content\'] of options, expected string or integer but ' . gettype($will['content']) . ' provided.');
            }
            if ($this->checkInvalidQos($will['qos'])) {
                throw new InvalidArgumentException('Bad will qos:' . var_export($will['qos'], true));
            }
            $this->_options['will'] = $options['will'];
        }

        // 重连时间间隔，默认 1 秒，0代表不重连
        if (isset($options['reconnect_period'])) {
            $reconnect_period = (int) $options['reconnect_period'];
            if (!static::isString($reconnect_period)) {
                throw new InvalidArgumentException('Bad reconnect_period of options, expected integer but ' . gettype($options['reconnect_period']) . ' provided.');
            }
            if ($reconnect_period < 0) {
                throw new InvalidArgumentException('Bad reconnect_period, expected integer which not less than 0 but ' . $options['reconnect_period'] . ' provided.');
            }
            $this->_options['reconnect_period'] = $reconnect_period;
        }

        if (isset($options['connect_timeout'])) {
            $connect_timeout = (int) $options['connect_timeout'];
            if (!static::isString($connect_timeout)) {
                throw new InvalidArgumentException('Bad connect_timeout of options, expected integer but ' . gettype($options['connect_timeout']) . ' provided.');
            }
            if ($connect_timeout <= 0) {
                throw new InvalidArgumentException('Bad connect_timeout, expected integer which greater than 0 but ' . $options['connect_timeout'] . ' provided.');
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

        // MQTT5 中所需要的属性
        if (!empty($options['properties'])) {
            $this->_options['properties'] = $options['properties'];
            if (empty($this->_options['protocol_level'])) {
                $this->_options['protocol_level'] = 5;
            }
        } else {
            $this->_options['properties'] = [];
        }
    }
}
