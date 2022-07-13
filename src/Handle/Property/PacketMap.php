<?php

namespace Workerman\Mqtt\Handle\Property;

use Workerman\Mqtt\Consts\PropertyConst;

/**
 * @see https://docs.oasis-open.org/mqtt/mqtt/v5.0/os/mqtt-v5.0-os.html#_Toc3901029
 * @see https://cloud.tencent.com/developer/article/1967534
 * MQTT 5.0 协议中携带有效载荷的报文有 ：
 * CONNECT，PUBLISH、SUBSCRIBE、SUBACK、UNSUBSCRIBE、UNSUBACK
 */
class PacketMap
{

    /**
     * CONNECT 报文携带 Payload，在可变头部新增的属性有
     * @var string[]
     */
    public static $connect = [
        PropertyConst::SESSION_EXPIRY_INTERVAL => 'session_expiry_interval',
        PropertyConst::AUTHENTICATION_METHOD => 'authentication_method',
        PropertyConst::AUTHENTICATION_DATA => 'authentication_data',
        PropertyConst::REQUEST_PROBLEM_INFORMATION => 'request_problem_information',
        PropertyConst::REQUEST_RESPONSE_INFORMATION => 'request_response_information',
        PropertyConst::RECEIVE_MAXIMUM => 'receive_maximum',
        PropertyConst::TOPIC_ALIAS_MAXIMUM => 'topic_alias_maximum',
        PropertyConst::USER_PROPERTY => 'user_property',
        PropertyConst::MAXIMUM_PACKET_SIZE => 'maximum_packet_size',
    ];

    /**
     * CONNACK 在可变头部中包含的属性有
     * @var string[]
     */
    public static $connAck = [
        PropertyConst::SESSION_EXPIRY_INTERVAL => 'session_expiry_interval',
        PropertyConst::ASSIGNED_CLIENT_IDENTIFIER => 'assigned_client_identifier',
        PropertyConst::SERVER_KEEP_ALIVE => 'server_keep_alive',
        PropertyConst::AUTHENTICATION_METHOD => 'authentication_method',
        PropertyConst::AUTHENTICATION_DATA => 'authentication_data',
        PropertyConst::RESPONSE_INFORMATION => 'response_information',
        PropertyConst::SERVER_REFERENCE => 'server_reference',
        PropertyConst::REASON_STRING => 'reason_string',
        PropertyConst::RECEIVE_MAXIMUM => 'receive_maximum',
        PropertyConst::TOPIC_ALIAS_MAXIMUM => 'topic_alias_maximum',
        PropertyConst::MAXIMUM_QOS => 'maximum_qos',
        PropertyConst::RETAIN_AVAILABLE => 'retain_available',
        PropertyConst::USER_PROPERTY => 'user_property',
        PropertyConst::MAXIMUM_PACKET_SIZE => 'maximum_packet_size',
        PropertyConst::WILDCARD_SUBSCRIPTION_AVAILABLE => 'wildcard_subscription_available',
        PropertyConst::SUBSCRIPTION_IDENTIFIER_AVAILABLE => 'subscription_identifier_available',
        PropertyConst::SHARED_SUBSCRIPTION_AVAILABLE => 'shared_subscription_available',
    ];

    /**
     * PUBLISH 报文携带 Payload，在可变头部的属性有
     * @var string[]
     */
    public static $publish = [
        PropertyConst::PAYLOAD_FORMAT_INDICATOR => 'payload_format_indicator',
        PropertyConst::MESSAGE_EXPIRY_INTERVAL => 'message_expiry_interval',
        PropertyConst::CONTENT_TYPE => 'content_type',
        PropertyConst::RESPONSE_TOPIC => 'response_topic',
        PropertyConst::CORRELATION_DATA => 'correlation_data',
        PropertyConst::SUBSCRIPTION_IDENTIFIER => 'subscription_identifier',
        PropertyConst::TOPIC_ALIAS => 'topic_alias',
        PropertyConst::USER_PROPERTY => 'user_property',
    ];

    /**
     * pubAck, pubRec, pubRel, pubComp, subAck, unSubAck 都具备以下属性
     */
    public static $pubAndSub = [
        PropertyConst::REASON_STRING => 'reason_string',
        PropertyConst::USER_PROPERTY => 'user_property',
    ];

    /**
     * SUBSCRIBE 报文携带 Payload，属性同样存在可变头部中
     * @var string[]
     */
    public static $subscribe = [
        PropertyConst::SUBSCRIPTION_IDENTIFIER => 'subscription_identifier',
        PropertyConst::USER_PROPERTY => 'user_property',
    ];

    /**
     * UNSUBSCRIBE 报文携带 Payload，仅有两个属性：属性长度和用户属性
     * @var string[]
     */
    public static $unSubscribe = [
        PropertyConst::USER_PROPERTY => 'user_property',
    ];

    /**
     * DISCONNECT 报文是 MQTT 5.0 新增的报文，它的引入意味着 mqtt broker 拥有了主动断开连接的能力。
     * DISCONNECT 报文所具备的属性有
     * @var string[]
     */
    public static $disConnect = [
        PropertyConst::SESSION_EXPIRY_INTERVAL => 'session_expiry_interval',
        PropertyConst::SERVER_REFERENCE => 'server_reference',
        PropertyConst::REASON_STRING => 'reason_string',
        PropertyConst::USER_PROPERTY => 'user_property',
    ];

    public static $auth = [
        PropertyConst::AUTHENTICATION_METHOD => 'authentication_method',
        PropertyConst::AUTHENTICATION_DATA => 'authentication_data',
        PropertyConst::REASON_STRING => 'reason_string',
        PropertyConst::USER_PROPERTY => 'user_property',
    ];

    public static $willProperties = [
        PropertyConst::PAYLOAD_FORMAT_INDICATOR => 'payload_format_indicator',
        PropertyConst::MESSAGE_EXPIRY_INTERVAL => 'message_expiry_interval',
        PropertyConst::CONTENT_TYPE => 'content_type',
        PropertyConst::RESPONSE_TOPIC => 'response_topic',
        PropertyConst::CORRELATION_DATA => 'correlation_data',
        PropertyConst::WILL_DELAY_INTERVAL => 'will_delay_interval',
        PropertyConst::USER_PROPERTY => 'user_property',
    ];
}
