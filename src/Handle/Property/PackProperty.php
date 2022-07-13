<?php

namespace Workerman\Mqtt\Handle\Property;

use Workerman\Mqtt\Consts\PropertyConst;
use Workerman\Mqtt\Handle\EncodeTrait;

class PackProperty
{
    public static function connect(array $data): string
    {
        $length = 0;
        $tmpBody = '';
        $connect = array_flip(PacketMap::$connect);
        foreach ($data as $key => $item) {
            if (isset($connect[$key])) {
                $property = $connect[$key];
                $tmpBody .= chr($property);
                switch ($property) {
                    case PropertyConst::SESSION_EXPIRY_INTERVAL:
                        $length += 5;
                        $tmpBody .= EncodeTrait::longInt($item);
                        break;
                    case PropertyConst::AUTHENTICATION_METHOD:
                    case PropertyConst::AUTHENTICATION_DATA:
                        $length += 3;
                        $length += strlen($item);
                        $tmpBody .= EncodeTrait::packString($item);
                        break;
                    case PropertyConst::REQUEST_PROBLEM_INFORMATION:
                    case PropertyConst::REQUEST_RESPONSE_INFORMATION:
                        $length += 2;
                        $tmpBody .= chr((int) $item);
                        break;
                    case PropertyConst::RECEIVE_MAXIMUM:
                    case PropertyConst::TOPIC_ALIAS_MAXIMUM:
                    case PropertyConst::MAXIMUM_PACKET_SIZE:
                        $length += 3;
                        $tmpBody .= EncodeTrait::shortInt($item);
                        break;
                }
            } else {
                // Property::USER_PROPERTY
                $length += 5;
                $length += strlen((string) $key);
                $length += strlen((string) $item);
                $tmpBody .= chr($connect['user_property']);
                $tmpBody .= EncodeTrait::stringPair((string) $key, (string) $item);
            }
        }

        return chr($length) . $tmpBody;
    }

    public static function willProperties(array $data): string
    {
        $length = 0;
        $tmpBody = '';
        $willProperties = array_flip(PacketMap::$willProperties);
        foreach ($data as $key => $item) {
            if (isset($willProperties[$key])) {
                $property = $willProperties[$key];
                $tmpBody .= chr($property);
                switch ($property) {
                    case PropertyConst::MESSAGE_EXPIRY_INTERVAL:
                    case PropertyConst::WILL_DELAY_INTERVAL:
                        $length += 5;
                        $tmpBody .= EncodeTrait::longInt($item);
                        break;
                    case PropertyConst::CONTENT_TYPE:
                    case PropertyConst::RESPONSE_TOPIC:
                    case PropertyConst::CORRELATION_DATA:
                        $length += 3;
                        $length += strlen($item);
                        $tmpBody .= EncodeTrait::packString($item);
                        break;
                    case PropertyConst::PAYLOAD_FORMAT_INDICATOR:
                        $length += 2;
                        $tmpBody .= chr((int) $item);
                        break;
                }
            } else {
                // Property::USER_PROPERTY
                $length += 5;
                $length += strlen((string) $key);
                $length += strlen((string) $item);
                $tmpBody .= chr($willProperties['user_property']);
                $tmpBody .= EncodeTrait::stringPair((string) $key, (string) $item);
            }
        }

        return chr($length) . $tmpBody;
    }

    public static function connAck(array $data): string
    {
        $length = 0;
        $tmpBody = '';
        $connAck = array_flip(PacketMap::$connAck);
        foreach ($data as $key => $item) {
            if (isset($connAck[$key])) {
                $property = $connAck[$key];
                $tmpBody .= chr($property);
                switch ($property) {
                    case PropertyConst::SESSION_EXPIRY_INTERVAL:
                    case PropertyConst::MAXIMUM_PACKET_SIZE:
                        $length += 5;
                        $tmpBody .= EncodeTrait::longInt($item);
                        break;
                    case PropertyConst::SERVER_KEEP_ALIVE:
                    case PropertyConst::RECEIVE_MAXIMUM:
                    case PropertyConst::TOPIC_ALIAS_MAXIMUM:
                        $length += 3;
                        $tmpBody .= EncodeTrait::shortInt($item);
                        break;
                    case PropertyConst::ASSIGNED_CLIENT_IDENTIFIER:
                    case PropertyConst::AUTHENTICATION_METHOD:
                    case PropertyConst::AUTHENTICATION_DATA:
                    case PropertyConst::RESPONSE_INFORMATION:
                    case PropertyConst::SERVER_REFERENCE:
                    case PropertyConst::REASON_STRING:
                        $length += 3;
                        $length += strlen($item);
                        $tmpBody .= EncodeTrait::packString($item);
                        break;
                    case PropertyConst::MAXIMUM_QOS:
                    case PropertyConst::RETAIN_AVAILABLE:
                    case PropertyConst::WILDCARD_SUBSCRIPTION_AVAILABLE:
                    case PropertyConst::SUBSCRIPTION_IDENTIFIER_AVAILABLE:
                    case PropertyConst::SHARED_SUBSCRIPTION_AVAILABLE:
                        $length += 2;
                        $tmpBody .= chr((int) $item);
                        break;
                }
            } else {
                // Property::USER_PROPERTY
                $length += 5;
                $length += strlen((string) $key);
                $length += strlen((string) $item);
                $tmpBody .= chr($connAck['user_property']);
                $tmpBody .= EncodeTrait::stringPair((string) $key, (string) $item);
            }
        }

        return chr($length) . $tmpBody;
    }

    public static function publish(array $data): string
    {
        $length = 0;
        $tmpBody = '';
        $publish = array_flip(PacketMap::$publish);
        foreach ($data as $key => $item) {
            if (isset($publish[$key])) {
                $property = $publish[$key];
                $tmpBody .= chr($property);
                switch ($property) {
                    case PropertyConst::MESSAGE_EXPIRY_INTERVAL:
                        $length += 5;
                        $tmpBody .= EncodeTrait::longInt($item);
                        break;
                    case PropertyConst::TOPIC_ALIAS:
                        $length += 3;
                        $tmpBody .= EncodeTrait::shortInt($item);
                        break;
                    case PropertyConst::CONTENT_TYPE:
                    case PropertyConst::RESPONSE_TOPIC:
                    case PropertyConst::CORRELATION_DATA:
                        $length += 3;
                        $length += strlen($item);
                        $tmpBody .= EncodeTrait::packString($item);
                        break;
                    case PropertyConst::PAYLOAD_FORMAT_INDICATOR:
                        $length += 2;
                        $tmpBody .= chr((int) $item);
                        break;
                    case PropertyConst::SUBSCRIPTION_IDENTIFIER:
                        $length += 1;
                        $value = EncodeTrait::varInt((int) $item);
                        $length += strlen($value);
                        $tmpBody .= $value;
                        break;
                }
            } else {
                // Property::USER_PROPERTY
                $length += 5;
                $length += strlen((string) $key);
                $length += strlen((string) $item);
                $tmpBody .= chr($publish['user_property']);
                $tmpBody .= EncodeTrait::stringPair((string) $key, (string) $item);
            }
        }

        return chr($length) . $tmpBody;
    }

    public static function pubAndSub(array $data): string
    {
        $length = 0;
        $tmpBody = '';
        $pubAndSub = array_flip(PacketMap::$pubAndSub);
        foreach ($data as $key => $item) {
            if (isset($pubAndSub[$key])) {
                $property = $pubAndSub[$key];
                $tmpBody .= chr($property);
                switch ($property) {
                    case PropertyConst::REASON_STRING:
                        $length += 3;
                        $length += strlen($item);
                        $tmpBody .= EncodeTrait::packString($item);
                        break;
                }
            } else {
                // Property::USER_PROPERTY
                $length += 5;
                $length += strlen((string) $key);
                $length += strlen((string) $item);
                $tmpBody .= chr($pubAndSub['user_property']);
                $tmpBody .= EncodeTrait::stringPair((string) $key, (string) $item);
            }
        }

        return chr($length) . $tmpBody;
    }

    public static function subscribe(array $data): string
    {
        $length = 0;
        $tmpBody = '';
        $subscribe = array_flip(PacketMap::$subscribe);
        foreach ($data as $key => $item) {
            if (isset($subscribe[$key])) {
                $property = $subscribe[$key];
                $tmpBody .= chr($property);
                switch ($property) {
                    case PropertyConst::SUBSCRIPTION_IDENTIFIER:
                        $length += 1;
                        $value = EncodeTrait::varInt((int) $item);
                        $length += strlen($value);
                        $tmpBody .= $value;
                        break;
                }
            } else {
                // Property::USER_PROPERTY
                $length += 5;
                $length += strlen((string) $key);
                $length += strlen((string) $item);
                $tmpBody .= chr($subscribe['user_property']);
                $tmpBody .= EncodeTrait::stringPair((string) $key, (string) $item);
            }
        }

        return chr($length) . $tmpBody;
    }

    public static function unSubscribe(array $data): string
    {
        $length = 0;
        $tmpBody = '';
        $unSubscribe = array_flip(PacketMap::$unSubscribe);
        foreach ($data as $key => $item) {
            // Property::USER_PROPERTY
            $length += 5;
            $length += strlen((string) $key);
            $length += strlen((string) $item);
            $tmpBody .= chr($unSubscribe['user_property']);
            $tmpBody .= EncodeTrait::stringPair((string) $key, (string) $item);
        }

        return chr($length) . $tmpBody;
    }

    public static function disConnect(array $data): string
    {
        $length = 0;
        $tmpBody = '';
        $disConnect = array_flip(PacketMap::$disConnect);
        foreach ($data as $key => $item) {
            if (isset($disConnect[$key])) {
                $property = $disConnect[$key];
                $tmpBody .= chr($property);
                switch ($property) {
                    case PropertyConst::SESSION_EXPIRY_INTERVAL:
                        $length += 5;
                        $tmpBody .= EncodeTrait::longInt($item);
                        break;
                    case PropertyConst::SERVER_REFERENCE:
                    case PropertyConst::REASON_STRING:
                        $length += 3;
                        $length += strlen($item);
                        $tmpBody .= EncodeTrait::packString($item);
                        break;
                }
            } else {
                // Property::USER_PROPERTY
                $length += 5;
                $length += strlen((string) $key);
                $length += strlen((string) $item);
                $tmpBody .= chr($disConnect['user_property']);
                $tmpBody .= EncodeTrait::stringPair((string) $key, (string) $item);
            }
        }

        return chr($length) . $tmpBody;
    }

    public static function auth(array $data): string
    {
        $length = 0;
        $tmpBody = '';
        $auth = array_flip(PacketMap::$auth);
        foreach ($data as $key => $item) {
            if (isset($auth[$key])) {
                $property = $auth[$key];
                $tmpBody .= chr($property);
                switch ($property) {
                    case PropertyConst::AUTHENTICATION_METHOD:
                    case PropertyConst::AUTHENTICATION_DATA:
                    case PropertyConst::REASON_STRING:
                        $length += 3;
                        $length += strlen($item);
                        $tmpBody .= EncodeTrait::packString($item);
                        break;
                }
            } else {
                // Property::USER_PROPERTY
                $length += 5;
                $length += strlen((string) $key);
                $length += strlen((string) $item);
                $tmpBody .= chr($auth['user_property']);
                $tmpBody .= EncodeTrait::stringPair((string) $key, (string) $item);
            }
        }

        return chr($length) . $tmpBody;
    }
}