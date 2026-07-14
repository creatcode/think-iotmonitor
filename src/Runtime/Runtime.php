<?php

namespace CreatCode\IotMonitor\Runtime;

use CreatCode\IotMonitor\Contracts\RuntimeAdapterInterface;

class Runtime
{
    /** @var RuntimeAdapterInterface|null */
    private static $adapter;

    public static function boot(?RuntimeAdapterInterface $adapter = null): RuntimeAdapterInterface
    {
        self::$adapter = $adapter ?: self::configuredAdapter() ?: self::detectAdapter();
        return self::$adapter;
    }

    public static function setAdapter(RuntimeAdapterInterface $adapter): void
    {
        self::$adapter = $adapter;
    }

    public static function adapter(): RuntimeAdapterInterface
    {
        if (self::$adapter === null) {
            self::boot();
        }

        return self::$adapter;
    }

    private static function detectAdapter(): RuntimeAdapterInterface
    {
        // ThinkPHP 6、7、8 使用 Facade。
        if (class_exists('think\\facade\\Cache')) {
            return new ThinkPhpFacadeAdapter();
        }

        // ThinkPHP 5.0、5.1 使用静态 Cache / Db 类。
        if (class_exists('think\\Cache')) {
            return new ThinkPhp5Adapter();
        }

        throw new \RuntimeException('No supported runtime was detected. Call Runtime::boot() with a custom adapter.');
    }

    /**
     * 读取显式框架配置；环境变量优先，避免 Composer 依赖共存时误判。
     */
    private static function configuredAdapter(): ?RuntimeAdapterInterface
    {
        $runtime = getenv('IOTMONITOR_RUNTIME');

        if ($runtime === false || $runtime === '') {
            $runtime = self::frameworkConfig('iotmonitor.runtime');
        }

        $runtime = strtolower(trim((string)($runtime ?: 'auto')));

        switch ($runtime) {
            case '':
            case 'auto':
                return null;
            case 'thinkphp5':
            case 'tp5':
            case 'thinkphp5.0':
            case 'thinkphp5.1':
                return new ThinkPhp5Adapter();
            case 'thinkphp':
            case 'tp6':
            case 'tp7':
            case 'tp8':
            case 'thinkphp6':
            case 'thinkphp7':
            case 'thinkphp8':
                return new ThinkPhpFacadeAdapter();
            default:
                throw new \InvalidArgumentException('Unsupported IOT monitor runtime: ' . $runtime);
        }
    }

    /**
     * 读取 ThinkPHP 全局配置；未启动框架时安全返回 null。
     */
    private static function frameworkConfig(string $name)
    {
        if (!function_exists('config')) {
            return null;
        }

        return config($name, null);
    }
}
