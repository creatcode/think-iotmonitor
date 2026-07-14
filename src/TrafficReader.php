<?php

declare(strict_types=1);

namespace CreatCode\IotMonitor;

/**
 * 按分钟存储聚合流量窗口。
 */
class TrafficReader
{
    /** @var StoreInterface */
    protected $store;

    public function __construct(StoreInterface $store)
    {
        $this->store = $store;
    }

    public function buildTrafficData(int $minutes): array
    {
        $minutes = max(1, min($minutes, 1440));
        $windowSet = array_values(array_unique(array_filter(array(1, 5, $minutes), function ($value) {
            return $value > 0;
        })));
        sort($windowSet);

        $windows = array();
        $stats = array();
        foreach ($windowSet as $windowMinutes) {
            $stats[$windowMinutes] = $this->sumMinuteTraffic($windowMinutes);
            $windows["{$windowMinutes}m"] = TrafficFormatter::formatWindow($stats[$windowMinutes], $windowMinutes);
        }

        $current = $windows['1m'] ?? TrafficFormatter::formatWindow(array(), 1);
        $mainWindowKey = "{$minutes}m";

        return array(
            'current' => $current,
            'main_window' => $mainWindowKey,
            'summary' => $windows[$mainWindowKey] ?? $current,
            'windows' => $windows,
            'protocols' => TrafficFormatter::parseProtocols($stats[$minutes] ?? array()),
        );
    }

    public function sumMinuteTraffic(int $minutes): array
    {
        $slot = intdiv(time(), 60) * 60;
        $summary = array();

        for ($i = 0; $i < $minutes; $i++) {
            $data = $this->store->getMinute(date('YmdHi', $slot - $i * 60));
            foreach ($data as $field => $value) {
                $summary[$field] = ($summary[$field] ?? 0) + (int)$value;
            }
        }

        return $summary;
    }
}
