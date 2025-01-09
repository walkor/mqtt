<?php

namespace Workerman\Mqtt\Consts;

/**
 * MQTTConst
 */
class MQTTConst
{
    public const MQTT_PROTOCOL_LEVEL_3_1 = 3;

    public const MQTT_PROTOCOL_LEVEL_3_1_1 = 4;

    public const MQTT_PROTOCOL_LEVEL_5_0 = 5;

    public const MQISDP_PROTOCOL_NAME = 'MQIsdp';

    public const MQTT_PROTOCOL_NAME = 'MQTT';

    public const MQTT_QOS_0 = 0;

    public const MQTT_QOS_1 = 1;

    public const MQTT_QOS_2 = 2;

    public const MQTT_RETAIN_0 = 0;

    public const MQTT_RETAIN_1 = 1;

    public const MQTT_RETAIN_2 = 2;

    public const MQTT_DUP_0 = 0;

    public const MQTT_DUP_1 = 1;

    public const MQTT_SESSION_PRESENT_0 = 0;

    public const MQTT_SESSION_PRESENT_1 = 1;

    /**
     * CONNECT Packet.
     */
    public const CMD_CONNECT = 1;

    /**
     * CONNACK
     */
    public const CMD_CONNACK = 2;

    /**
     * PUBLISH
     */
    public const CMD_PUBLISH = 3;

    /**
     * PUBACK
     */
    public const CMD_PUBACK = 4;

    /**
     * PUBREC
     */
    public const CMD_PUBREC = 5;

    /**
     * PUBREL
     */
    public const CMD_PUBREL = 6;

    /**
     * PUBCOMP
     */
    public const CMD_PUBCOMP = 7;

    /**
     * SUBSCRIBE
     */
    public const CMD_SUBSCRIBE = 8;

    /**
     * SUBACK
     */
    public const CMD_SUBACK = 9;

    /**
     * UNSUBSCRIBE
     */
    public const CMD_UNSUBSCRIBE = 10;

    /**
     * UNSUBACK
     */
    public const CMD_UNSUBACK = 11;

    /**
     * PINGREQ
     */
    public const CMD_PINGREQ = 12;

    /**
     * PINGRESP
     */
    public const CMD_PINGRESP = 13;

    /**
     * DISCONNECT
     */
    public const CMD_DISCONNECT = 14;

    /**
     * Authentication exchange
     */
    public const CMD_AUTH = 15;
}
