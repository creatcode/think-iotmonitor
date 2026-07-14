<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CreatCode\IotMonitor\StoreInterface;
use CreatCode\IotMonitor\TrafficReader;

$store = new class implements StoreInterface {
    public function incrementMinute(string $minute, array $fields, int $ttl): void
    {
    }

    public function getMinute(string $minute): array
    {
        return array(
            'rx_bytes' => 1024,
            'tx_bytes' => 1024,
            'rx_packets' => 2,
            'tx_packets' => 1,
            'modbus:rx_bytes' => 1024,
        );
    }
};

$data = (new TrafficReader($store))->buildTrafficData(1);

if ($data['summary']['total_bytes'] !== 2048 || $data['summary']['total_format'] !== '2 KB') {
    throw new RuntimeException('Traffic window was not formatted.');
}

if (($data['protocols'][0]['protocol'] ?? '') !== 'modbus') {
    throw new RuntimeException('Protocol traffic was not parsed.');
}

echo "Traffic reader test passed\n";
