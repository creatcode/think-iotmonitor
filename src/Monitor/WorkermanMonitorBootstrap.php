<?php

declare(strict_types=1);

namespace CreatCode\IotMonitor\Monitor;

use CreatCode\IotMonitor\ManagerHelper;
use CreatCode\IotMonitor\TrafficMonitor;

/**
 * ThinkPHP 与 Workerman TCP 进程共用的流量监控启动器。
 */
class WorkermanMonitorBootstrap
{
    /** @var bool */
    protected static $initialized = false;

    /**
     * 在创建 Workerman Worker 前或 onWorkerStart 中调用一次。
     */
    public static function boot(): void
    {
        if (self::$initialized) {
            return;
        }

        self::validateProtocolConfig();
        TrafficMonitor::init(new AppTrafficStore(), array(
            'enable' => ManagerHelper::trafficEnabled(),
            'flush_interval' => ManagerHelper::config('traffic.flush_interval', 5),
            'retention_seconds' => ManagerHelper::config('traffic.retention_seconds', 86400),
        ));
        self::$initialized = true;
    }

    /**
     * 校验 Workerman 协议解析所需的扩展包长度配置。
     */
    private static function validateProtocolConfig(): void
    {
        if (!ManagerHelper::pluginEnabled()) {
            return;
        }

        $extraPackets = ManagerHelper::config('protocol.extra_packets', array());
        if (!is_array($extraPackets)) {
            throw new \RuntimeException('iotmonitor protocol.extra_packets must be an array.');
        }

        foreach ($extraPackets as $tag => $length) {
            if ($length === null || (is_string($length) && trim($length) === '')) {
                throw new \RuntimeException('iotmonitor protocol.extra_packets.' . $tag . ' cannot be empty.');
            }
        }
    }
}
