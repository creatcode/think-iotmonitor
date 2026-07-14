<?php

namespace CreatCode\ThinkIotMonitor;

use CreatCode\ThinkIotMonitor\Runtime\Runtime;

class ManagerHelper
{
    public static function config(string $name, $default = null)
    {
        $value = Runtime::adapter()->config($name, $default);
        if (!is_array($value) || strpos($name, '.') === false) {
            return $value;
        }

        return $value;
    }

    public static function dbConfig(string $name, $default = null)
    {
        return self::config('db.' . $name, $default);
    }

    /**
     * 获取当前 ThinkPHP 运行时可读取的物联网监控配置。
     *
     * @return array
     */
    public static function pluginConfig(): array
    {
        $config = self::config('', array());
        return is_array($config) ? $config : array();
    }

    public static function boolConfig(string $name, bool $default = false): bool
    {
        $value = self::config($name, $default);
        return is_bool($value) ? $value : !in_array(strtolower(trim((string)$value)), ['0', 'false', 'off', 'no'], true);
    }

    /**
     * 判断监控功能总开关是否开启。
     */
    public static function pluginEnabled(): bool
    {
        return self::boolConfig('enable', true);
    }

    /**
     * 判断流量监控是否开启。
     */
    public static function trafficEnabled(): bool
    {
        return self::pluginEnabled() && self::boolConfig('traffic.enable', false);
    }

    public static function deviceActiveTimeKey(): string
    {
        if (!self::pluginEnabled() || !self::boolConfig('overview.enable', false)) {
            return '';
        }

        return (string)self::config('overview.redis_keys.device_active_time', 'DeviceActiveTime');
    }

    public static function runtimePath(): string
    {
        return Runtime::adapter()->runtimePath();
    }

    public static function log(string $fileName, $message): void
    {
        $dir = self::runtimePath() . DIRECTORY_SEPARATOR . 'iotlog';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($dir . DIRECTORY_SEPARATOR . $fileName, print_r($message, true) . PHP_EOL, FILE_APPEND);
    }
}
