<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (class_exists('CreatCode\\ThinkIotMonitor\\Http\\RouteRegistrar') || class_exists('CreatCode\\ThinkIotMonitor\\Http\\ThinkService')) {
    throw new RuntimeException('Monitor package must not contain ThinkPHP route registration classes.');
}

if (!class_exists('CreatCode\\ThinkIotMonitor\\Http\\MonitorController')) {
    throw new RuntimeException('Monitor controller is missing.');
}

echo "HTTP resources test passed\n";
