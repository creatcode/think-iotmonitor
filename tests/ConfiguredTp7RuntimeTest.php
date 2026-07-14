<?php

namespace think\facade {
    class Cache
    {
    }
}

namespace {
    require dirname(__DIR__) . '/vendor/autoload.php';

    use CreatCode\IotMonitor\Runtime\Runtime;
    use CreatCode\IotMonitor\Runtime\ThinkPhpFacadeAdapter;

    putenv('IOTMONITOR_RUNTIME=tp7');
    Runtime::boot();

    if (!(Runtime::adapter() instanceof ThinkPhpFacadeAdapter)) {
        throw new RuntimeException('Configured ThinkPHP 7 runtime was not selected.');
    }

    echo "Configured ThinkPHP 7 runtime test passed\n";
}
