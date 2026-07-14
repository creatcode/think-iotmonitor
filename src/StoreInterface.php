<?php

declare(strict_types=1);

namespace CreatCode\IotMonitor;

/**
 * 分钟流量统计的存储抽象。
 */
interface StoreInterface
{
    public function incrementMinute(string $minute, array $fields, int $ttl): void;

    public function getMinute(string $minute): array;
}
