<?php

namespace CreatCode\ThinkIotMonitor\Runtime;

use CreatCode\ThinkIotMonitor\Contracts\RuntimeAdapterInterface;

/**
 * ThinkPHP 6、8 的 Facade 运行时适配器。
 */
class ThinkPhpFacadeAdapter implements RuntimeAdapterInterface
{
    public function redis(bool $forceReconnect = false)
    {
        if (!class_exists('think\\facade\\Cache')) {
            throw new \RuntimeException('ThinkPHP Facade Cache class is unavailable.');
        }

        return \think\facade\Cache::store('redis')->handler();
    }

    public function pingDb(): void
    {
        if (!class_exists('think\\facade\\Db')) {
            throw new \RuntimeException('ThinkPHP Facade Db class is unavailable.');
        }

        \think\facade\Db::query('SELECT 1');
    }

    public function disconnectDb(): void
    {
        if (class_exists('think\\facade\\Db') && method_exists('think\\facade\\Db', 'disconnect')) {
            \think\facade\Db::disconnect();
        }
    }

    public function config(string $name, $default = null)
    {
        return function_exists('config') ? config('iotmonitor.' . $name, $default) : $default;
    }

    public function runtimePath(): string
    {
        if (defined('RUNTIME_PATH')) {
            return rtrim(RUNTIME_PATH, '/\\');
        }

        return function_exists('runtime_path')
            ? rtrim(runtime_path(), '/\\')
            : getcwd() . DIRECTORY_SEPARATOR . 'runtime';
    }
}
