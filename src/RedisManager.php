<?php

namespace CreatCode\ThinkIotMonitor;

use CreatCode\ThinkIotMonitor\Runtime\Runtime;

class RedisManager
{
    private static $redis;
    private static $lastPingAt = 0;

    public static function get(bool $forceReconnect = false)
    {
        if ($forceReconnect || self::$redis === null) {
            self::$redis = Runtime::adapter()->redis($forceReconnect);
            self::$lastPingAt = 0;
        }

        return self::$redis;
    }

    /**
     * 获取底层 Redis 连接，保留旧版本公共方法。
     */
    public static function connection(bool $forceReconnect = false)
    {
        return self::get($forceReconnect);
    }

    public static function reconnect()
    {
        if (self::$redis && method_exists(self::$redis, 'close')) {
            self::$redis->close();
        }

        self::$redis = null;
        return self::get(true);
    }

    public static function ping(): void
    {
        $interval = (int)ManagerHelper::dbConfig('redis_ping_interval', 15);
        if ($interval > 0 && time() - self::$lastPingAt < $interval) {
            return;
        }

        $result = self::get()->ping();
        if ($result !== true && stripos((string)$result, 'PONG') === false) {
            throw new \RuntimeException('Redis ping failed.');
        }

        self::$lastPingAt = time();
    }

    public static function call(callable $callback, $default = null, bool $swallowException = false, bool $retry = true)
    {
        try {
            self::ping();
            return $callback(self::get());
        } catch (\Throwable $exception) {
            if (!$retry || !self::isConnectionException($exception)) {
                if ($swallowException) {
                    return $default;
                }
                throw $exception;
            }

            try {
                return $callback(self::reconnect());
            } catch (\Throwable $retryException) {
                if ($swallowException) {
                    return $default;
                }
                throw $retryException;
            }
        }
    }

    public static function execute(string $method, array $arguments = [], $default = null, bool $swallowException = false)
    {
        if (in_array(strtolower($method), array('scan', 'hscan', 'sscan', 'zscan'), true)) {
            throw new \InvalidArgumentException('Cursor-based Redis commands must use their dedicated RedisManager methods.');
        }

        return self::call(function ($redis) use ($method, $arguments) {
            return call_user_func_array([$redis, $method], $arguments);
        }, $default, $swallowException, self::isReadCommand($method));
    }

    public static function pipeline(callable $callback): array
    {
        return self::call(function ($redis) use ($callback) {
            $redis->multi(\Redis::PIPELINE);
            $callback($redis);
            $result = $redis->exec();
            if ($result === false) {
                throw new \RuntimeException('Redis pipeline execution failed.');
            }
            return $result;
        }, [], false, false);
    }

    /**
     * 执行允许降级的写命令，连接异常时返回默认值。
     */
    public static function safeWrite(string $method, array $arguments = array(), $default = null, bool $retryOnConnectionException = true)
    {
        return self::call(function ($redis) use ($method, $arguments) {
            return call_user_func_array(array($redis, $method), $arguments);
        }, $default, true, $retryOnConnectionException);
    }

    /**
     * 执行允许降级的 Redis pipeline。
     */
    public static function safePipeline(callable $callback, array $default = array(), bool $retryOnConnectionException = true): array
    {
        try {
            return self::call(function ($redis) use ($callback) {
                $redis->multi(\Redis::PIPELINE);
                $callback($redis);
                $result = $redis->exec();
                if ($result === false) {
                    throw new \RuntimeException('Redis pipeline execution failed.');
                }
                return $result;
            }, array(), false, $retryOnConnectionException);
        } catch (\Throwable $exception) {
            ManagerHelper::log('redis.log', '[' . date('Y-m-d H:i:s') . '] safe pipeline fail: ' . $exception->getMessage());
            return $default;
        }
    }

    public static function scan(&$iterator, $pattern = null, int $count = 0)
    {
        return self::call(function ($redis) use (&$iterator, $pattern, $count) {
            return $count > 0 ? $redis->scan($iterator, $pattern, $count) : $redis->scan($iterator, $pattern);
        }, false, false, true);
    }

    /**
     * 扫描 Hash，保留游标引用参数。
     */
    public static function hScan(string $key, &$iterator, $pattern = null, int $count = 0)
    {
        return self::call(function ($redis) use ($key, &$iterator, $pattern, $count) {
            return $count > 0 ? $redis->hScan($key, $iterator, $pattern, $count) : $redis->hScan($key, $iterator, $pattern);
        }, false, false, true);
    }

    /**
     * 扫描 Set，保留游标引用参数。
     */
    public static function sScan(string $key, &$iterator, $pattern = null, int $count = 0)
    {
        return self::call(function ($redis) use ($key, &$iterator, $pattern, $count) {
            return $count > 0 ? $redis->sScan($key, $iterator, $pattern, $count) : $redis->sScan($key, $iterator, $pattern);
        }, false, false, true);
    }

    /**
     * 扫描有序集合，保留游标引用参数。
     */
    public static function zScan(string $key, &$iterator, $pattern = null, int $count = 0)
    {
        return self::call(function ($redis) use ($key, &$iterator, $pattern, $count) {
            return $count > 0 ? $redis->zScan($key, $iterator, $pattern, $count) : $redis->zScan($key, $iterator, $pattern);
        }, false, false, true);
    }

    public static function __callStatic($method, $arguments)
    {
        return self::execute((string)$method, $arguments);
    }

    private static function isReadCommand(string $method): bool
    {
        return in_array(strtolower($method), array('get', 'mget', 'hget', 'hmget', 'hgetall', 'hexists', 'hkeys', 'hvals', 'hlen', 'llen', 'lrange', 'scard', 'sismember', 'smembers', 'srandmember', 'zcard', 'zcount', 'zrange', 'zrevrange', 'zrangebyscore', 'zrevrangebyscore', 'zscore', 'exists', 'ttl', 'pttl', 'type', 'lindex', 'keys'), true);
    }

    private static function isConnectionException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        foreach (['connection', 'socket', 'broken pipe', 'timed out', 'refused', 'gone away'] as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
