<?php

namespace CreatCode\ThinkIotMonitor\Runtime;

use CreatCode\ThinkIotMonitor\Contracts\RuntimeAdapterInterface;

class ThinkPhp5Adapter implements RuntimeAdapterInterface
{
    private $redisFactory;
    private $config;

    public function __construct(?callable $redisFactory = null, array $config = [])
    {
        $this->redisFactory = $redisFactory;
        $this->config = $config;
    }

    public function redis(bool $forceReconnect = false)
    {
        if ($this->redisFactory !== null) {
            return call_user_func($this->redisFactory, $forceReconnect);
        }

        if (!class_exists('think\\Cache')) {
            throw new \RuntimeException('ThinkPHP 5 Cache class is unavailable.');
        }

        return \think\Cache::store('redis')->handler();
    }

    public function pingDb(): void
    {
        \think\Db::query('SELECT 1');
    }

    public function disconnectDb(): void
    {
        if (method_exists('think\\Db', 'close')) {
            \think\Db::close();
        }
    }

    public function config(string $name, $default = null)
    {
        if (array_key_exists($name, $this->config)) {
            return $this->config[$name];
        }

        if (function_exists('config')) {
            return config('iotmonitor.' . $name, $default);
        }

        return $default;
    }

    public function runtimePath(): string
    {
        return defined('RUNTIME_PATH') ? rtrim(RUNTIME_PATH, '/\\') : getcwd() . DIRECTORY_SEPARATOR . 'runtime';
    }
}
