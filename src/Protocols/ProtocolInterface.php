<?php

declare(strict_types=1);

namespace Workerman\Mqtt\Protocols;

use Workerman\Connection\ConnectionInterface;

interface ProtocolInterface extends \Workerman\Protocols\ProtocolInterface
{
    /**
     * 初始化
     *
     * @param string $address
     * @param array $options
     * @param array $context
     * @return string class-string
     */
    public static function init(string &$address, array &$options, array &$context): string;

    /**
     * 数据打包
     *
     * @param mixed $data
     * @param ConnectionInterface $connection
     * @return mixed
     */
    public static function pack(mixed $data, ConnectionInterface $connection): mixed;

    /**
     * 数据解包
     *
     * @param mixed $package
     * @param ConnectionInterface $connection
     * @return mixed
     */
    public static function unpack(mixed $package, ConnectionInterface $connection): mixed;
}
