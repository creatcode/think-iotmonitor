<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__, 2) . '/iotadmin-new';
define('APP_PATH', $projectRoot . '/application/');
require $projectRoot . '/thinkphp/base.php';

use CreatCode\ThinkIotMonitor\Runtime\Runtime;
use CreatCode\ThinkIotMonitor\Runtime\ThinkPhp5Adapter;

Runtime::boot();

if (!(Runtime::adapter() instanceof ThinkPhp5Adapter)) {
    throw new RuntimeException('iotadmin-new was not detected as a ThinkPHP 5 runtime.');
}

echo "ThinkPHP 5 detection test passed\n";
