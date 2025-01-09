<?php

namespace Workerman\Mqtt\Tools;

/**
 * @method static string hexDump(string $encode) // 以16进制显示
 * @method static string hexDumpAscii(string $encode) // 以16进制和相应的ASCII字符显示
 * @method static string printableText(string $encode) // 可打印字符
 * @method static string hexStream(string $encode) // 16进制流
 * @method static string ascii(string $encode) // 以ASCII字符显示
 */
abstract class Common
{
    public static function printf(string $data)
    {
        echo "\033[36m";
        for ($i = 0, $iMax = strlen($data); $i < $iMax; $i++) {
            $ascii = ord($data[$i]);
            if ($ascii > 31) {
                $chr = $data[$i];
            } else {
                $chr = ' ';
            }
            printf("%4d: %08b : 0x%02x : %d : %s\n", $i, $ascii, $ascii, $ascii, $chr);
        }
        echo "\033[0m";
    }

    public static function __callStatic($method, $arguments)
    {
        return (new Debug())->setEncode(...$arguments)->{$method}();
    }
}
