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

use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\Ws as BaseWs;

/**
 * Mqtt over ws support
 *
 * @author    chaz6chez<chaz6chez1993@outlook.com>
 */
class Ws implements ProtocolInterface
{

    /** @var string {@see BaseWs::BINARY_TYPE_BLOB} */
    public const BINARY_TYPE_BLOB = BaseWs::BINARY_TYPE_BLOB;

    /** @var string {@see BaseWs::BINARY_TYPE_ARRAYBUFFER} */
    public const BINARY_TYPE_ARRAYBUFFER = BaseWs::BINARY_TYPE_ARRAYBUFFER;


    /** @inheritdoc  */
    public static function init(string &$address, array &$options, array &$context): string
    {
        class_alias($className = self::class, '\Protocols\Ws');
        if ($options['ssl'] and is_array($options['ssl'])) {
            $context['ssl'] = $options['ssl'];
        }
        $context['socket'] = ['bindto' => $options['bindto']];
        if (str_starts_with($address, 'wss')) {
            $options['ssl'] = empty($options['ssl']) ? true : $options['ssl'];
            $address = str_replace('wss', 'ws', $address);
        }
        $address = $address . ($options['uri'] ?? '/mqtt');
        return $className;
    }

    /** @inheritdoc  */
    public static function pack(mixed $data, ConnectionInterface $connection): mixed
    {
        /** @var ProtocolInterface $protocol */
        $protocol = $connection->websocketClientDataProtocolClass ?? null;
        if ($protocol) {
            return $protocol::encode($data, $connection);
        }
        return $data;
    }

    /** @inheritdoc  */
    public static function unpack(mixed $package, ConnectionInterface $connection): mixed
    {
        /** @var ProtocolInterface $protocol */
        $protocol = $connection->websocketClientDataProtocolClass ?? null;
        if ($protocol) {
            return $protocol::decode($package, $connection);
        }
        return $package;
    }

    /** @inheritdoc  */
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        if ($connection instanceof AsyncTcpConnection) {
            return BaseWs::input($buffer, $connection);
        }
        throw new \RuntimeException('Not support. ');
    }

    /** @inheritdoc  */
    public static function decode(string $buffer, ConnectionInterface $connection): mixed
    {
        if ($connection instanceof AsyncTcpConnection) {
            return BaseWs::decode($buffer, $connection);
        }
        throw new \RuntimeException('Not support. ');
    }

    /** @inheritdoc  */
    public static function encode(mixed $data, ConnectionInterface $connection): string
    {
        if ($connection instanceof AsyncTcpConnection) {
            try {
                return BaseWs::encode($data, $connection);
            } catch (\Throwable $t) {
                throw new \RuntimeException($t->getMessage(), $t->getCode());
            }
        }
        throw new \RuntimeException('Not support. ');
    }
}
