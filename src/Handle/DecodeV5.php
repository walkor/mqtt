<?php

namespace Workerman\Mqtt\Handle;

use Workerman\Mqtt\Consts\MQTTConst;
use Workerman\Mqtt\Consts\ReasonCodeConst;
use Workerman\Mqtt\Handle\Property\UnPackProperty;

/**
 * DecodeV5
 */
class DecodeV5
{
    public static function connect(string $body): array
    {
        $protocol_name = Decoder::readString($body);
        $protocol_level = ord($body[0]);
        $clean_session = ord($body[1]) >> 1 & 0x1;
        $will_flag = ord($body[1]) >> 2 & 0x1;
        $will_qos = ord($body[1]) >> 3 & 0x3;
        $will_retain = ord($body[1]) >> 5 & 0x1;
        $password_flag = ord($body[1]) >> 6 & 0x1;
        $userName_flag = ord($body[1]) >> 7 & 0x1;
        $body = substr($body, 2);
        $keepAlive = Decoder::shortInt($body);
        $properties_total_length = Decoder::byte($body);
        if ($properties_total_length) {
            $properties = UnPackProperty::connect($properties_total_length, $body);
        }
        $clientId = Decoder::readString($body);
        $will_properties = [];
        if ($will_flag) {
            $will_properties_total_length = Decoder::byte($body);
            if ($will_properties_total_length) {
                $will_properties = UnPackProperty::willProperties($will_properties_total_length, $body);
            }
            $will_topic = Decoder::readString($body);
            $will_content = Decoder::readString($body);
        }
        $userName = $password = '';
        if ($userName_flag) {
            $userName = Decoder::readString($body);
        }
        if ($password_flag) {
            $password = Decoder::readString($body);
        }

        $package = [
            'cmd'            => MQTTConst::CMD_CONNECT,
            'protocol_name'  => $protocol_name,
            'protocol_level' => $protocol_level,
            'clean_session'  => $clean_session,
            'properties'     => [],
            'will'           => [],
            'username'       => $userName,
            'password'       => $password,
            'keepalive'      => $keepAlive,
            'client_id'      => $clientId,
        ];

        if ($properties_total_length) {
            $package['properties'] = $properties;
        } else {
            unset($package['properties']);
        }

        //        $package['client_id'] = $clientId;

        if ($will_flag) {
            if ($will_properties_total_length) {
                $package['will']['properties'] = $will_properties;
            }
            $package['will'] += [
                'qos'     => $will_qos,
                'retain'  => $will_retain,
                'topic'   => $will_topic,
                'message' => $will_content,
            ];
        } else {
            unset($package['will']);
        }

        return $package;
    }

    public static function connAck(string $body): array
    {
        $session_present = ord($body[0]) & 0x01;
        $code = ord($body[1]);
        $body = substr($body, 2);

        $package = [
            'cmd'             => MQTTConst::CMD_CONNACK,
            'session_present' => $session_present,
            'code'            => $code,
        ];

        $properties_total_length = Decoder::byte($body);
        if ($properties_total_length) {
            $package['properties'] = UnPackProperty::connAck($properties_total_length, $body);
        }

        return $package;
    }

    public static function publish(int $dup, int $qos, int $retain, string $body): array
    {
        $topic = Decoder::readString($body);

        $package = [
            'cmd'    => MQTTConst::CMD_PUBLISH,
            'dup'    => $dup,
            'qos'    => $qos,
            'retain' => $retain,
            'topic'  => $topic,
        ];

        if ($qos) {
            $package['message_id'] = Decoder::shortInt($body);
        }

        $properties_total_length = Decoder::byte($body);
        if ($properties_total_length) {
            $package['properties'] = UnPackProperty::publish($properties_total_length, $body);
        }

        $package['content'] = $body;

        return $package;
    }

    public static function subscribe(string $body): array
    {
        $message_id = Decoder::shortInt($body);
        $package = [
            'cmd'        => MQTTConst::CMD_SUBSCRIBE,
            'message_id' => $message_id,
        ];

        $properties_total_length = Decoder::byte($body);
        if ($properties_total_length) {
            $package['properties'] = UnPackProperty::subscribe($properties_total_length, $body);
        }

        $topics = [];
        while ($body) {
            $topic = Decoder::readString($body);
            $topics[$topic] = [
                'qos'                 => ord($body[0]) & 0x3,
                'no_local'            => (bool) (ord($body[0]) >> 2 & 0x1),
                'retain_as_published' => (bool) (ord($body[0]) >> 3 & 0x1),
                'retain_handling'     => ord($body[0]) >> 4,
            ];
            $body = substr($body, 1);
        }

        $package['topics'] = $topics;

        return $package;
    }

    public static function subAck(string $body): array
    {
        $message_id = Decoder::shortInt($body);

        $package = [
            'cmd'        => MQTTConst::CMD_SUBACK,
            'message_id' => $message_id,
        ];

        $properties_total_length = Decoder::byte($body);
        if ($properties_total_length) {
            $package['properties'] = UnPackProperty::pubAndSub($properties_total_length, $body);
        }

        $codes = unpack('C*', $body);
        $package['codes'] = array_values($codes);

        return $package;
    }

    public static function unSubscribe(string $body): array
    {
        $message_id = Decoder::shortInt($body);

        $package = [
            'cmd'        => MQTTConst::CMD_UNSUBSCRIBE,
            'message_id' => $message_id,
        ];

        $properties_total_length = Decoder::byte($body);
        if ($properties_total_length) {
            $package['properties'] = UnPackProperty::unSubscribe($properties_total_length, $body);
        }

        $topics = [];
        while ($body) {
            $topic = Decoder::readString($body);
            $topics[] = $topic;
        }

        $package['topics'] = $topics;

        return $package;
    }

    public static function unSubAck(string $body): array
    {
        $message_id = Decoder::shortInt($body);

        $package = [
            'cmd'        => MQTTConst::CMD_UNSUBACK,
            'message_id' => $message_id,
        ];

        $properties_total_length = Decoder::byte($body);
        if ($properties_total_length) {
            $package['properties'] = UnPackProperty::pubAndSub($properties_total_length, $body);
        }

        $codes = unpack('C*', $body);
        $package['codes'] = array_values($codes);

        return $package;
    }

    public static function disconnect(string $body): array
    {
        if (isset($body[0])) {
            $code = Decoder::byte($body);
        } else {
            $code = ReasonCodeConst::NORMAL_DISCONNECTION;
        }
        $package = [
            'cmd'  => MQTTConst::CMD_DISCONNECT,
            'code' => $code,
        ];

        $properties_total_length = 0;
        if (isset($body[0])) {
            $properties_total_length = Decoder::byte($body);
        }

        if ($properties_total_length) {
            $package['properties'] = UnPackProperty::disconnect($properties_total_length, $body);
        }

        return $package;
    }

    public static function getReasonCode(int $cmd, string $body): array
    {
        $message_id = Decoder::shortInt($body);

        if (isset($body[0])) {
            $code = Decoder::byte($body);
        } else {
            $code = ReasonCodeConst::SUCCESS;
        }

        $package = [
            'cmd'        => $cmd,
            'message_id' => $message_id,
            'code'       => $code,
        ];

        $properties_total_length = 0;
        if (isset($body[0])) {
            $properties_total_length = Decoder::byte($body);
        }

        if ($properties_total_length) {
            $package['properties'] = UnPackProperty::pubAndSub($properties_total_length, $body);
        }

        return $package;
    }

    public static function auth(string $data): array
    {
        if (isset($data[0])) {
            $code = Decoder::byte($data);
        } else {
            $code = ReasonCodeConst::SUCCESS;
        }
        $package = [
            'cmd'  => MQTTConst::CMD_AUTH,
            'code' => $code,
        ];

        $properties_total_length = 0;
        if (isset($data[0])) {
            $properties_total_length = Decoder::byte($data);
        }

        if ($properties_total_length) {
            $package['properties'] = UnPackProperty::auth($properties_total_length, $data);
        }

        return $package;
    }
}
