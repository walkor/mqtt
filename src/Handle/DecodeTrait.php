<?php

namespace Workerman\Mqtt\Handle;

use Workerman\Mqtt\Consts\MQTTConst;

trait DecodeTrait
{
    /**
     * Get cmd.
     *
     * @param string $buffer
     * @return int
     */
    public static function getCmd(string $buffer)
    {
        return ord($buffer[0]) >> 4;
    }

    /**
     * Read string from buffer.
     * @param string $buffer
     * @return string
     */
    public static function readString(string &$buffer)
    {
        $length = unpack('n', $buffer)[1];
        if ($length + 2 > strlen($buffer)) {
            echo "buffer:" . bin2hex($buffer) . " length:$length not enough for unpackString\n";
        }

        $string = substr($buffer, 2, $length);
        $buffer = substr($buffer, $length + 2);
        return $string;
    }

    /**
     * Read unsigned short int from buffer.
     * @param string $buffer
     * @return mixed
     */
    public static function shortInt(string &$buffer)
    {
        $tmp = unpack('n', $buffer);
        $buffer = substr($buffer, 2);

        return $tmp[1];
    }

    public static function longInt(string &$buffer)
    {
        $tmp = unpack('N', $buffer);
        $buffer = substr($buffer, 4);

        return $tmp[1];
    }

    public static function byte(string &$buffer)
    {
        $tmp = ord($buffer[0]);
        $buffer = substr($buffer, 1);

        return $tmp;
    }

    public static function varInt(string&$remaining,  &$len)
    {
        $body_length = static::getBodyLength($remaining, $head_bytes);
        $len = $head_bytes;

        $result = $shift = 0;
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($remaining[$i]);
            $result |= ($byte & 0x7F) << $shift++ * 7;
        }

        $remaining = substr($remaining, $head_bytes, $body_length);

        return $result;
    }

    /**
     * Get body length.
     *
     * @param string $buffer
     * @param null|int $head_bytes
     * @return int
     */
    public static function getBodyLength(string $buffer,  &$head_bytes)
    {
        $head_bytes = $multiplier = 1;
        $value = 0;
        do {
            if (!isset($buffer[$head_bytes])) {
                throw new \Exception('Malformed Remaining Length');
//                $head_bytes = 0;
//                return 0;
            }
            $digit = ord($buffer[$head_bytes]);
            $value += ($digit & 127) * $multiplier;
            $multiplier *= 128;
            ++$head_bytes;
        } while (($digit & 128) != 0);

        return $value;
    }

    /**
     * Get body.
     *
     * @param string $buffer
     * @return string
     */
    public static function getBody(string $buffer)
    {
        $body_length = static::getBodyLength($buffer, $head_bytes);
        return substr($buffer, $head_bytes, $body_length);
    }

    /**
     * Get the MQTT protocol level.
     *
     * @param string $data
     * @return int
     */
    public static function getLevel(string $data)
    {
        $cmd = static::getCmd($data);

        if ($cmd !== MQTTConst::CMD_CONNECT) {
            throw new \InvalidArgumentException(
                sprintf('packet must be of type connect, cmd value equal %s given', $cmd)
            );
        }

        $remaining = static::getBody($data);
        $length = unpack('n', $remaining)[1];

        return ord($remaining[$length + 2]);
    }
}