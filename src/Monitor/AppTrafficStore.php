<?php

declare(strict_types=1);

namespace CreatCode\ThinkIotMonitor\Monitor;

use CreatCode\ThinkIotMonitor\RedisManager;
use CreatCode\ThinkIotMonitor\StoreInterface;

/**
 * 使用 Redis Hash 保存分钟流量统计。
 */
class AppTrafficStore implements StoreInterface
{
    public function incrementMinute(string $minute, array $fields, int $ttl): void
    {
        $key = 'MonitorTraffic:minute:' . $minute;
        RedisManager::pipeline(function ($redis) use ($key, $fields, $ttl) {
            foreach ($fields as $field => $value) {
                if ($value > 0) {
                    $redis->hIncrBy($key, $field, $value);
                }
            }
            $redis->expire($key, $ttl);
        });
    }

    public function getMinute(string $minute): array
    {
        return RedisManager::hGetAll('MonitorTraffic:minute:' . $minute) ?: array();
    }
}
