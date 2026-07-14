<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CreatCode\ThinkIotMonitor\Protocol\ModbusTcpProtocol;

$frame = "\x00\x01\x00\x00\x00\x06\x01\x03\x00\x00\x00\x01";

if (ModbusTcpProtocol::getFrameLength($frame) !== 12) {
    throw new RuntimeException('Modbus TCP frame length was not parsed.');
}

if (ModbusTcpProtocol::getFrameLength("\x00\x01\x00\x01\x00\x06") !== -1) {
    throw new RuntimeException('Invalid Modbus TCP protocol id was accepted.');
}

echo "Modbus TCP protocol test passed\n";
