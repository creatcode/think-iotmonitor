<?php

namespace CreatCode\ThinkIotMonitor;

use CreatCode\ThinkIotMonitor\Runtime\Runtime;

class DbManager
{
    private static $lastPingAt = 0;

    public static function ping(): void
    {
        $interval = (int)ManagerHelper::dbConfig('db_ping_interval', 30);
        if ($interval > 0 && time() - self::$lastPingAt < $interval) {
            return;
        }

        Runtime::adapter()->pingDb();
        self::$lastPingAt = time();
    }

    /**
     * 断开并重置数据库连接状态。
     */
    public static function reconnect(): void
    {
        Runtime::adapter()->disconnectDb();
        self::$lastPingAt = 0;
    }

    public static function call(callable $callback)
    {
        try {
            self::ping();
            return $callback();
        } catch (\Throwable $exception) {
            if (!self::isConnectionException($exception)) {
                throw $exception;
            }

            self::reconnect();
            return $callback();
        }
    }

    private static function isConnectionException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        foreach (array('server has gone away', 'lost connection', 'connection reset', 'connection refused', 'broken pipe', 'server closed the connection unexpectedly', 'communication link failure', 'packet sequence number wrong', 'sqlstate[hy000] [2002]', 'sqlstate[hy000] [2006]', 'sqlstate[hy000] [2013]', 'php_network_getaddresses', 'network is unreachable', 'no route to host', 'timed out') as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
