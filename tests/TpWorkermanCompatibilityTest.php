<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CreatCode\ThinkIotMonitor\Monitor\OverviewReader;
use CreatCode\ThinkIotMonitor\Monitor\WorkermanMonitorBootstrap;
use CreatCode\ThinkIotMonitor\Protocol\ModbusRtuProtocol;

if (!class_exists(WorkermanMonitorBootstrap::class)) {
    throw new RuntimeException('Workerman monitor bootstrap is unavailable.');
}

if (class_exists('CreatCode\\ThinkIotMonitor\\Runtime\\WebmanAdapter')) {
    throw new RuntimeException('Webman runtime adapter must not be included in the ThinkPHP package.');
}

if (!class_exists(OverviewReader::class)) {
    throw new RuntimeException('Overview reader must remain available.');
}

if (ModbusRtuProtocol::crc16Modbus("\x01\x03\x00\x00\x00\x0A") !== 0xCDC5) {
    throw new RuntimeException('Workerman Modbus RTU protocol must remain available.');
}

$composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);
if (($composer['name'] ?? null) !== 'creatcode/think-iotmonitor') {
    throw new RuntimeException('Composer package name must be creatcode/think-iotmonitor.');
}

if (($composer['autoload']['psr-4']['CreatCode\\ThinkIotMonitor\\'] ?? null) !== 'src/') {
    throw new RuntimeException('Composer PSR-4 namespace must be CreatCode\\ThinkIotMonitor\\.');
}

if (($composer['extra']['class'] ?? null) !== 'CreatCode\\ThinkIotMonitor\\Composer\\Plugin') {
    throw new RuntimeException('Composer plugin class must use the current namespace.');
}

if (stripos(json_encode($composer), 'webman') !== false) {
    throw new RuntimeException('composer.json must not contain Webman dependencies or metadata.');
}

foreach (array(
    'src/Runtime/WebmanAdapter.php',
    'src/Install.php',
    'src/config/plugin/creatcode/iotmonitor/app.php',
    'src/plugin/iotmonitor/app/controller/IndexController.php',
) as $path) {
    if (file_exists(dirname(__DIR__) . '/' . $path)) {
        throw new RuntimeException('Webman-only package resource still exists: ' . $path);
    }
}

echo "ThinkPHP Workerman compatibility test passed\n";
