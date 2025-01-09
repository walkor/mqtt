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

namespace Workerman\Mqtt\Protocols;

use Workerman\Mqtt\Consts\MQTTConst;
use Workerman\Mqtt\Handle\Decoder;
use Workerman\Mqtt\Handle\DecodeV5;
use Workerman\Mqtt\Handle\Encoder;
use Workerman\Mqtt\Handle\EncodeV5;
use Workerman\Connection\ConnectionInterface;

/**
 * Mqtt5 Protocol.
 *
 * @author    maoxps
 */
class Mqtt5 implements ProtocolInterface
{
    /** @inheritdoc */
    public static function init(string &$address, array &$options, array &$context): string
    {
        $className = self::class;
        // mqtt version check
        if ($options['protocol_level'] !== 5) {
            $className = Mqtt::class;
        }
        class_alias($className, '\Workerman\Protocols\Mqtt');
        // context
        if ($options['bindto']) {
            $context['socket'] = ['bindto' => $options['bindto']];
        }
        if ($options['ssl'] and is_array($options['ssl'])) {
            $context['ssl'] = $options['ssl'];
        }
        // address override
        if (str_starts_with($address, 'mqtts')) {
            $options['ssl'] = empty($options['ssl']) ? true : $options['ssl'];
            $address = str_replace('mqtts', 'mqtt', $address);
        }
        return $className;
    }

    /** @inheritdoc */
    public static function pack(mixed $data, ConnectionInterface $connection): mixed
    {
        return $data;
    }

    /** @inheritdoc */
    public static function unpack(mixed $package, ConnectionInterface $connection): mixed
    {
        return $package;
    }

    /**
     * Check the integrity of the package.
     *
     *  如果可以在$buffer中得到请求包的长度则返回整个包的长度
     *  否则返回0，表示需要更多的数据才能得到当前请求包的长度
     *  如果返回false或者负数，则代表错误的请求，则连接会断开
     *
     * @inheritdoc
     */
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        $length = strlen($buffer);
        $body_length = Decoder::getBodyLength($buffer, $head_bytes);
        $total_length = $body_length + $head_bytes;
        if ($length < $total_length) {
            return 0;
        }

        return $total_length;
    }

    /**
     * 用于请求打包
     *
     * 当需要向客户端发送数据即调用$connection->send($data);时
     * 会自动把$data用encode打包一次，变成符合协议的数据格式，然后再发送给客户端
     * 也就是说发送给客户端的数据会自动encode打包，无需业务代码中手动调用
     *
     * @inheritdoc
     */
    public static function encode(mixed $data, ConnectionInterface $connection): string
    {
        $cmd = $data['cmd'];

        return match ($cmd) {
            MQTTConst::CMD_CONNECT => EncodeV5::connect($data),
            MQTTConst::CMD_CONNACK => EncodeV5::connAck($data),
            MQTTConst::CMD_PUBLISH => EncodeV5::publish($data),
            MQTTConst::CMD_PUBACK, MQTTConst::CMD_PUBREC, MQTTConst::CMD_PUBREL, MQTTConst::CMD_PUBCOMP => EncodeV5::genReasonPhrase($data),
            MQTTConst::CMD_SUBSCRIBE   => EncodeV5::subscribe($data),
            MQTTConst::CMD_SUBACK      => EncodeV5::subAck($data),
            MQTTConst::CMD_UNSUBSCRIBE => EncodeV5::unSubscribe($data),
            MQTTConst::CMD_UNSUBACK    => EncodeV5::unSubAck($data),
            MQTTConst::CMD_PINGREQ, MQTTConst::CMD_PINGRESP => Encoder::packHead($cmd, 0),
            MQTTConst::CMD_DISCONNECT => EncodeV5::disconnect($data),
            MQTTConst::CMD_AUTH       => EncodeV5::auth($data),
            default                   => throw new \InvalidArgumentException('MQTT Type not exist'),
        };
    }

    /**
     * 用于请求解包
     *
     * input返回值大于0，并且WorkerMan收到了足够的数据，则自动调用decode
     * 然后触发onMessage回调，并将decode解码后的数据传递给onMessage回调的第二个参数
     * 也就是说当收到完整的客户端请求时，会自动调用decode解码，无需业务代码中手动调用
     *
     * @inheritdoc
     */
    public static function decode(string $buffer, ConnectionInterface $connection): array
    {
        //        var_dump((new Debug($buffer))->hexDumpAscii());
        $cmd = Decoder::getCmd($buffer);
        $body = Decoder::getBody($buffer);

        return match ($cmd) {
            MQTTConst::CMD_CONNECT => DecodeV5::connect($body),
            MQTTConst::CMD_CONNACK => DecodeV5::connAck($body),
            MQTTConst::CMD_PUBLISH => call_user_func(function () use ($buffer, $body) {
                $dup = ord($buffer[0]) >> 3 & 0x1;
                $qos = ord($buffer[0]) >> 1 & 0x3;
                $retain = ord($buffer[0]) & 0x1;

                return DecodeV5::publish($dup, $qos, $retain, $body);
            }),
            MQTTConst::CMD_PUBACK, MQTTConst::CMD_PUBREC, MQTTConst::CMD_PUBREL, MQTTConst::CMD_PUBCOMP => DecodeV5::getReasonCode($cmd, $body),
            MQTTConst::CMD_SUBSCRIBE   => DecodeV5::subscribe($body),
            MQTTConst::CMD_SUBACK      => DecodeV5::subAck($body),
            MQTTConst::CMD_UNSUBSCRIBE => DecodeV5::unSubscribe($body),
            MQTTConst::CMD_UNSUBACK    => DecodeV5::unSubAck($body),
            MQTTConst::CMD_PINGREQ, MQTTConst::CMD_PINGRESP => ['cmd' => $cmd],
            MQTTConst::CMD_DISCONNECT => DecodeV5::disconnect($body),
            MQTTConst::CMD_AUTH       => DecodeV5::auth($body),
            default                   => [],
        };
    }
}
