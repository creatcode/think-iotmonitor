<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CreatCode\IotMonitor\Runtime\Runtime;
use CreatCode\IotMonitor\Runtime\ThinkPhp5Adapter;

putenv('IOTMONITOR_RUNTIME=thinkphp5');
Runtime::boot();

if (!(Runtime::adapter() instanceof ThinkPhp5Adapter)) {
    throw new RuntimeException('Configured ThinkPHP 5 runtime was not selected.');
}

echo "Configured runtime test passed\n";
