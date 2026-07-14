<?php

namespace CreatCode\IotMonitor\Contracts;

interface RuntimeAdapterInterface
{
    public function redis(bool $forceReconnect = false);

    public function pingDb(): void;

    public function disconnectDb(): void;

    public function config(string $name, $default = null);

    public function runtimePath(): string;
}
