<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CreatCode\ThinkIotMonitor\Protocol\BaseProtocol;
use CreatCode\ThinkIotMonitor\Protocol\LoRaProtocol;
use CreatCode\ThinkIotMonitor\Protocol\ModbusRtuProtocol;
use CreatCode\ThinkIotMonitor\Protocol\ModbusTcpProtocol;
use CreatCode\ThinkIotMonitor\Protocol\TemperatureProtocol;
use CreatCode\ThinkIotMonitor\Runtime\Runtime;
use CreatCode\ThinkIotMonitor\Runtime\ThinkPhp5Adapter;

// 使用明确配置隔离协议测试，避免依赖完整 ThinkPHP 启动流程。
Runtime::setAdapter(new ThinkPhp5Adapter(null, array(
    'protocol.extra_packets' => array(
        'imei' => 19,
        'ping' => 4,
    ),
)));

$inputMethod = new ReflectionMethod(BaseProtocol::class, 'input');
if (!$inputMethod->isPublic() || !$inputMethod->isFinal()) {
    throw new RuntimeException('Base protocol input must remain the final public entry point.');
}

foreach (array(LoRaProtocol::class, ModbusRtuProtocol::class, ModbusTcpProtocol::class, TemperatureProtocol::class) as $protocolClass) {
    $payloadMethod = new ReflectionMethod($protocolClass, 'inputPayload');
    if (!$payloadMethod->isProtected()) {
        throw new RuntimeException($protocolClass . ' inputPayload must remain protected.');
    }
}

set_error_handler(static function ($severity, $message) {
    throw new ErrorException($message, 0, $severity);
});

try {
    $shortLoRa = LoRaProtocol::decode("\x6c\x02\x11");
} finally {
    restore_error_handler();
}

if ($shortLoRa['type'] !== 'report' || $shortLoRa['protocol'] !== 'lora') {
    throw new RuntimeException('Short LoRa packets must decode safely with a protocol identifier.');
}

$imeiPacket = "imei-device-payload";
$decodedImei = ModbusTcpProtocol::decode($imeiPacket);
if ($decodedImei['type'] !== 'imei' || $decodedImei['data'] !== $imeiPacket || $decodedImei['protocol'] !== 'mtcp') {
    throw new RuntimeException('Configured Modbus TCP extra packets were not decoded correctly.');
}

$report = "\x00\x01\x00\x00\x00\x06\x01\x03\x00\x00\x00\x01";
$decodedReport = ModbusTcpProtocol::decode($report);
if ($decodedReport['type'] !== 'report' || $decodedReport['data'] !== bin2hex($report) || $decodedReport['protocol'] !== 'mtcp') {
    throw new RuntimeException('Modbus TCP reports must include normalized data and protocol fields.');
}

$temperaturePacket = "\x01\x02\x03\x04\x05\x0f\x00";
if (TemperatureProtocol::validateReportPacket($temperaturePacket) !== TemperatureProtocol::REPORT_PACKET_SIZE) {
    throw new RuntimeException('Temperature report checksum was not validated.');
}

if (ModbusTcpProtocol::encode('0A ff') !== "\x0a\xff") {
    throw new RuntimeException('Protocol hex encoding normalization failed.');
}

echo "Protocol behavior test passed\n";
