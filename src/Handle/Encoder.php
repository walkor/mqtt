<?php

namespace Workerman\Mqtt\Handle;

class Encoder
{
    /**
     * Pack string.
     *
     * @param string $str
     * @return string
     */
    public static function packString(string $str): string
    {
        $len = strlen($str);
        return pack('n', $len) . $str;
    }

    public static function stringPair(string $key, string $value): string
    {
        return static::packString($key) . static::packString($value);
    }

    public static function longInt(int $int): string
    {
        return pack('N', $int);
    }

    public static function shortInt(int $int): string
    {
        return pack('n', $int);
    }

    public static function varInt(int $int): string
    {
        return static::writeBodyLength($int);
    }

    /**
     * packHead.
     *
     * @param int $cmd
     * @param int $body_length
     * @param int $dup
     * @param int $qos
     * @param int $retain
     * @return string
     */
    public static function packHead(int $cmd,
                                    int $body_length,
                                    int $dup = 0, int $qos = 0, int $retain = 0): string
    {
        $cmd = $cmd << 4;
        if ($dup) {
            $cmd |= 1 << 3;
        }
        if ($qos) {
            $cmd |= $qos << 1;
        }
        if ($retain) {
            $cmd |= 1;
        }
        return chr($cmd) . static::writeBodyLength($body_length);
    }

    /**
     * Write body length.
     *
     * @param int $len
     * @return string
     */
    protected static function writeBodyLength(int$len): string
    {
        $string = '';
        do {
            $digit = $len % 128;
            $len = $len >> 7;
            // if there are more digits to encode, set the top bit of this digit
            if ($len > 0) {
                $digit = ($digit | 0x80);
            }
            $string .= chr($digit);
        } while ($len > 0);

        return $string;
    }
}