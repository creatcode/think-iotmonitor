<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CreatCode\ThinkIotMonitor\StoreInterface;
use CreatCode\ThinkIotMonitor\TrafficMonitor;

$store = new class implements StoreInterface {
    public $writes = array();

    public function incrementMinute(string $minute, array $fields, int $ttl): void
    {
        $this->writes[] = array($minute, $fields, $ttl);
    }

    public function getMinute(string $minute): array
    {
        return array();
    }
};

TrafficMonitor::init($store, array('flush_interval' => 60, 'retention_seconds' => 120));
TrafficMonitor::recordIncoming('modbus', 12, true);
TrafficMonitor::flush();

if (($store->writes[0][1]['rx_bytes'] ?? 0) !== 12 || ($store->writes[0][1]['report_packets'] ?? 0) !== 1) {
    throw new RuntimeException('Traffic monitor did not flush packet metrics.');
}

echo "Traffic monitor test passed\n";
