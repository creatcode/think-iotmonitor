<?php

namespace think\facade {
    class Cache
    {
        public static $storeName;

        public static function store($name)
        {
            self::$storeName = $name;

            return new class {
                public function handler()
                {
                    return 'redis-handler';
                }
            };
        }
    }

    class Db
    {
        public static $queries = array();
        public static $disconnected = false;

        public static function query($sql)
        {
            self::$queries[] = $sql;
        }

        public static function disconnect()
        {
            self::$disconnected = true;
        }
    }
}

namespace {
    function config($name, $default = null)
    {
        return $name === 'iotmonitor.redis.timeout' ? 30 : $default;
    }

    define('RUNTIME_PATH', __DIR__ . '/runtime/');

    require dirname(__DIR__) . '/vendor/autoload.php';

    use CreatCode\ThinkIotMonitor\Runtime\Runtime;
    use CreatCode\ThinkIotMonitor\Runtime\ThinkPhpFacadeAdapter;

    Runtime::boot();

    if (!(Runtime::adapter() instanceof ThinkPhpFacadeAdapter)) {
        throw new RuntimeException('ThinkPHP facade runtime was not detected.');
    }

    if (Runtime::adapter()->redis() !== 'redis-handler' || \think\facade\Cache::$storeName !== 'redis') {
        throw new RuntimeException('ThinkPHP facade Redis store was not used.');
    }

    Runtime::adapter()->pingDb();
    Runtime::adapter()->disconnectDb();

    if (\think\facade\Db::$queries !== array('SELECT 1') || !\think\facade\Db::$disconnected) {
        throw new RuntimeException('ThinkPHP facade database methods were not used.');
    }

    if (Runtime::adapter()->config('redis.timeout') !== 30) {
        throw new RuntimeException('ThinkPHP facade configuration was not read.');
    }

    if (Runtime::adapter()->runtimePath() !== rtrim(RUNTIME_PATH, '/\\')) {
        throw new RuntimeException('ThinkPHP facade runtime path was not read.');
    }

    echo "ThinkPHP facade detection test passed\n";
}
