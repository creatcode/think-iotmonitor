<?php

declare(strict_types=1);

namespace CreatCode\ThinkIotMonitor;

use Workerman\Timer;

/**
 * TCP 协议流量采集器，按分钟聚合后异步写入存储。
 */
class TrafficMonitor
{
    protected static $flushInterval = 5;
    protected static $retentionSeconds = 172800;
    protected static $enabled = true;
    protected static $store;
    protected static $buffer = array();
    protected static $timerStarted = false;
    protected static $flushing = false;
    protected static $cachedMinuteSlot = 0;
    protected static $cachedMinute = '';
    protected static $maxBufferMinutes = 10;

    public static function init(StoreInterface $store, array $config = array()): void
    {
        self::$store = $store;
        self::$enabled = (bool)($config['enable'] ?? true);
        self::$flushInterval = max(1, (int)($config['flush_interval'] ?? 5));
        self::$retentionSeconds = max(60, (int)($config['retention_seconds'] ?? 172800));
    }

    public static function isEnabled(): bool
    {
        return self::$enabled && self::$store !== null;
    }

    public static function recordIncoming(string $protocol, int $bytes, bool $isReport): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::record($protocol, 'rx', $bytes);
        if ($isReport) {
            self::recordReport($protocol);
        }
    }

    public static function record(string $protocol, string $direction, int $bytes): void
    {
        if (!self::isEnabled() || $bytes <= 0 || !in_array($direction, array('rx', 'tx'), true)) {
            return;
        }

        $protocol = $protocol !== '' ? $protocol : 'unknown';
        $minute = self::currentMinute();
        self::increment($minute, $direction . '_bytes', $bytes);
        self::increment($minute, $direction . '_packets', 1);
        self::increment($minute, $protocol . ':' . $direction . '_bytes', $bytes);
        self::increment($minute, $protocol . ':' . $direction . '_packets', 1);
    }

    public static function recordReport(string $protocol): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $protocol = $protocol !== '' ? $protocol : 'unknown';
        $minute = self::currentMinute();
        self::increment($minute, 'report_packets', 1);
        self::increment($minute, $protocol . ':report_packets', 1);
    }

    public static function flush(): void
    {
        if (self::$flushing || empty(self::$buffer) || self::$store === null) {
            return;
        }

        self::$flushing = true;
        $data = self::$buffer;
        self::$buffer = array();

        try {
            foreach ($data as $minute => $fields) {
                self::$store->incrementMinute((string)$minute, $fields, self::$retentionSeconds);
            }
        } catch (\Throwable $exception) {
            self::mergeBack($data);
            ManagerHelper::log('monitor.log', '[' . date('Y-m-d H:i:s') . '] traffic flush fail: ' . $exception->getMessage());
        } finally {
            self::$flushing = false;
        }
    }

    protected static function increment(string $minute, string $field, int $value): void
    {
        self::startTimer();
        if (!isset(self::$buffer[$minute])) {
            self::$buffer[$minute] = array();
        }
        self::$buffer[$minute][$field] = (self::$buffer[$minute][$field] ?? 0) + $value;
    }

    protected static function currentMinute(): string
    {
        $now = time();
        $slot = intdiv($now, 60);
        if (self::$cachedMinuteSlot !== $slot) {
            self::$cachedMinuteSlot = $slot;
            self::$cachedMinute = date('YmdHi', $now);
        }
        return self::$cachedMinute;
    }

    protected static function startTimer(): void
    {
        if (self::$timerStarted) {
            return;
        }

        self::$timerStarted = true;
        register_shutdown_function(array(self::class, 'flush'));

        try {
            Timer::add(self::$flushInterval, array(self::class, 'flush'));
        } catch (\Throwable $exception) {
            // 非 Workerman 事件循环环境依赖进程结束时的 flush。
        }
    }

    protected static function mergeBack(array $data): void
    {
        foreach ($data as $minute => $fields) {
            foreach ($fields as $field => $value) {
                self::$buffer[$minute][$field] = (self::$buffer[$minute][$field] ?? 0) + $value;
            }
        }

        if (count(self::$buffer) > self::$maxBufferMinutes) {
            ksort(self::$buffer);
            self::$buffer = array_slice(self::$buffer, -self::$maxBufferMinutes, null, true);
        }
    }
}
