<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CreatCode\IotMonitor\Runtime\Runtime;
use CreatCode\IotMonitor\Runtime\ThinkPhp5Adapter;

Runtime::setAdapter(new ThinkPhp5Adapter(function () {
    return new class {
        public function ping()
        {
            return '+PONG';
        }
    };
}));

$adapter = Runtime::adapter();
if (get_class($adapter) !== ThinkPhp5Adapter::class) {
    throw new RuntimeException('ThinkPHP 5 adapter was not retained.');
}

echo "Runtime compatibility test passed\n";
