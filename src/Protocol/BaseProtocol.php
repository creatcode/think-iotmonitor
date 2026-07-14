<?php

declare(strict_types=1);

namespace CreatCode\IotMonitor\Protocol;

use CreatCode\IotMonitor\ManagerHelper;
use CreatCode\IotMonitor\TrafficMonitor;
use Workerman\Connection\ConnectionInterface;

/**
 * TCP 协议基类，统一处理收发流量统计和特殊包配置。
 */
abstract class BaseProtocol
{
    protected static $extraPacketsCache;

    abstract public static function input($buffer, ConnectionInterface $connection);

    abstract protected static function decodePayload($buffer): array;

    public static function decode($buffer)
    {
        $result = static::decodePayload($buffer);
        if (TrafficMonitor::isEnabled()) {
            TrafficMonitor::recordIncoming(static::protocolName(), strlen($buffer), ($result['type'] ?? '') === 'report');
        }
        return $result;
    }

    public static function encode($buffer)
    {
        $buffer = (string)$buffer;
        if ($buffer === '') {
            return '';
        }
        if (!ctype_xdigit($buffer)) {
            $buffer = preg_replace('/[^a-fA-F0-9]/', '', $buffer);
            if ($buffer === '') {
                return '';
            }
        }
        if ((strlen($buffer) & 1) !== 0) {
            $buffer = '0' . $buffer;
        }
        $binary = hex2bin($buffer);
        if ($binary !== false && TrafficMonitor::isEnabled()) {
            TrafficMonitor::record(static::protocolName(), 'tx', strlen($binary));
        }
        return $binary;
    }

    protected static function extraPacketLength($tag): ?int
    {
        $length = static::extraPacketsConfig()[$tag] ?? null;
        return $length === null ? null : (int)$length;
    }

    protected static function closeInvalidConnection(ConnectionInterface $connection)
    {
        $connection->close();
        return 0;
    }

    protected static function protocolName(): string
    {
        return static::PROTOCOL_NAME;
    }

    protected static function protocolConfig(string $name, $default = null)
    {
        return ManagerHelper::config('protocol.' . $name, $default);
    }

    private static function extraPacketsConfig(): array
    {
        if (static::$extraPacketsCache === null) {
            $value = ManagerHelper::config('protocol.extra_packets', array());
            static::$extraPacketsCache = is_array($value) ? $value : array();
        }
        return static::$extraPacketsCache;
    }
}
