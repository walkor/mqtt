<?php

namespace Workerman\Mqtt\Consts;

/**
 * MQTTConst
 *
 * @package Workerman\Mqtt\Config
 */
class MQTTConst
{
    const MQTT_PROTOCOL_LEVEL_3_1 = 3;

    const MQTT_PROTOCOL_LEVEL_3_1_1 = 4;

    const MQTT_PROTOCOL_LEVEL_5_0 = 5;

    const MQISDP_PROTOCOL_NAME = 'MQIsdp';

    const MQTT_PROTOCOL_NAME = 'MQTT';

    const MQTT_QOS_0 = 0;

    const MQTT_QOS_1 = 1;

    const MQTT_QOS_2 = 2;

    const MQTT_RETAIN_0 = 0;

    const MQTT_RETAIN_1 = 1;

    const MQTT_RETAIN_2 = 2;

    const MQTT_DUP_0 = 0;

    const MQTT_DUP_1 = 1;

    const MQTT_SESSION_PRESENT_0 = 0;

    const MQTT_SESSION_PRESENT_1 = 1;

    /**
     * CONNECT Packet.
     */
    const CMD_CONNECT = 1;

    /**
     * CONNACK
     */
    const CMD_CONNACK = 2;

    /**
     * PUBLISH
     */
    const CMD_PUBLISH = 3;

    /**
     * PUBACK
     */
    const CMD_PUBACK = 4;

    /**
     * PUBREC
     */
    const CMD_PUBREC = 5;

    /**
     * PUBREL
     */
    const CMD_PUBREL = 6;

    /**
     * PUBCOMP
     */
    const CMD_PUBCOMP = 7;

    /**
     * SUBSCRIBE
     */
    const CMD_SUBSCRIBE = 8;

    /**
     * SUBACK
     */
    const CMD_SUBACK = 9;

    /**
     * UNSUBSCRIBE
     */
    const CMD_UNSUBSCRIBE = 10;

    /**
     * UNSUBACK
     */
    const CMD_UNSUBACK = 11;

    /**
     * PINGREQ
     */
    const CMD_PINGREQ = 12;

    /**
     * PINGRESP
     */
    const CMD_PINGRESP = 13;

    /**
     * DISCONNECT
     */
    const CMD_DISCONNECT = 14;

    /**
     * Authentication exchange
     */
    const CMD_AUTH = 15;
}