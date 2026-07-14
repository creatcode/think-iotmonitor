# creatcode/think-iotmonitor

面向 ThinkPHP、FastAdmin 与 Workerman TCP 服务的物联网监控组件。

组件提供协议拆包、Redis 与数据库连接管理、TCP 流量统计、设备与队列总览，以及无需注册框架路由的独立监控页面。

## 功能

- 支持 ThinkPHP 5.0、5.1、6、7、8，以及使用 `topthink/framework: dev-master` 的 FastAdmin 项目。
- 支持 Workerman 4、5。
- 内置 Modbus TCP、Modbus RTU、LoRa、温度协议辅助能力。
- 提供 Redis 与数据库连接探活、断线重连及安全调用方法。
- 支持 TCP 收发流量、上报包数的分钟级 Redis 统计。
- 提供设备活跃状态、上报积压、Redis 队列和 Workerman 进程总览。
- Composer 安装或更新时自动发布配置、独立监控入口和静态资源。
- 监控入口不依赖 ThinkPHP 路由，关闭路由、绑定模块或使用自定义后台入口时仍可访问。

## 环境要求

- PHP `^7.2 || ^8.0`
- `ext-redis`
- `topthink/framework`：ThinkPHP 5、6、7、8 或 FastAdmin 使用的 `dev-master`
- `workerman/workerman`：4 或 5
- Composer 1.1+ 或 Composer 2

TP6、TP7、TP8 使用数据库 Facade 时，建议安装：

```bash
composer require topthink/think-orm
```

## 安装

```bash
composer require creatcode/think-iotmonitor
```

Composer 2.2 及以上版本需要在项目 `composer.json` 中允许插件执行：

```json
{
  "config": {
    "allow-plugins": {
      "creatcode/think-iotmonitor": true
    }
  }
}
```

安装或更新后，插件会自动发布以下内容：

| 环境 | 配置文件 |
| --- | --- |
| TP5 / TP5.1 | `{app-path}/extra/iotmonitor.php`，默认是 `application/extra/iotmonitor.php` |
| TP6 / TP7 / TP8 | `config/iotmonitor.php` |

同时发布：

```text
public/iotmonitor.php
public/iotmonitor-assets/
```

已有同名配置、入口或资源不会在安装、更新时被覆盖。

## 配置

发布后的 `iotmonitor.php` 提供以下主要配置：

```php
<?php

return [
    // 组件总开关。
    'enable' => true,

    'traffic' => [
        // 是否启用 TCP 流量统计。
        'enable' => false,

        // 内存统计数据写入 Redis 的间隔，单位秒。
        'flush_interval' => 5,

        // 分钟统计数据的保留时间，单位秒。
        'retention_seconds' => 86400,
    ],

    'db' => [
        // 数据库连接探活间隔，单位秒。
        'db_ping_interval' => 30,

        // Redis 连接探活间隔，单位秒。
        'redis_ping_interval' => 15,
    ],

    'protocol' => [
        // 是否校验 Modbus RTU CRC。
        'rtu_crc_check' => false,

        // 特殊报文长度配置，key 为报文标识，value 为完整报文长度。
        'extra_packets' => [
            'imei' => 19,
            'ping' => 4,
        ],
    ],

    'overview' => [
        // 是否启用监控总览。
        'enable' => false,

        // 需要统计的 Redis 队列名称。
        'queues' => [
            'login_command',
            'dev_link',
            'dev_alarm',
        ],
    ],
];
```

完整配置项以发布后的 `iotmonitor.php` 文件为准。修改配置后，需重启对应的 Workerman TCP Worker。

## Workerman 接入

在每个 TCP Worker 的 `onWorkerStart` 中初始化运行时与监控组件：

```php
use CreatCode\ThinkIotMonitor\Monitor\WorkermanMonitorBootstrap;
use CreatCode\ThinkIotMonitor\Runtime\Runtime;

$worker->onWorkerStart = function () {
    Runtime::boot();
    WorkermanMonitorBootstrap::boot();
};
```

使用内置协议时，将协议类配置给对应 Worker。例如 Modbus TCP：

```php
use CreatCode\ThinkIotMonitor\Protocol\ModbusTcpProtocol;

$worker->protocol = ModbusTcpProtocol::class;
```

内置协议类：

```text
CreatCode\ThinkIotMonitor\Protocol\ModbusTcpProtocol
CreatCode\ThinkIotMonitor\Protocol\ModbusRtuProtocol
CreatCode\ThinkIotMonitor\Protocol\LoRaProtocol
CreatCode\ThinkIotMonitor\Protocol\TemperatureProtocol
```

## Redis 连接管理

```php
use CreatCode\ThinkIotMonitor\RedisManager;

// 读操作：连接异常时自动重连并重试一次。
$value = RedisManager::hGet('key', 'field');

// 安全写入：发生异常时返回默认值。
RedisManager::safeWrite('zAdd', ['Key', time(), 'member'], 0);

// 安全管道操作。
RedisManager::safePipeline(function ($redis) {
    $redis->incr('counter');
});
```

## 数据库连接管理

```php
use CreatCode\ThinkIotMonitor\DbManager;

$rows = DbManager::call(function () {
    return \think\facade\Db::name('device')->select();
});
```

TP5 项目使用 `\think\Db`；TP6 及以上项目使用 `\think\facade\Db`。

## 流量统计

启用 `traffic.enable` 后，组件会按分钟统计 TCP 收发字节数、收发包数、上报包数和各协议分项，并批量写入 Redis。

```php
use CreatCode\ThinkIotMonitor\TrafficReader;

$data = (new TrafficReader())->buildTrafficData(60);
```

支持 `5`、`60`、`360`、`1440` 分钟统计窗口。

## 监控总览

```php
use CreatCode\ThinkIotMonitor\Monitor\OverviewReader;

$data = (new OverviewReader())->build(60);
```

总览包含 TCP 流量统计、上报缓存积压、待处理设备上报数、Redis 队列状态、最近活跃设备数和 Workerman 进程信息。

## 监控页面

所有 ThinkPHP 版本均通过独立入口访问：

```text
/iotmonitor.php
```

总览数据接口：

```text
/iotmonitor.php/overview?minutes=60
```

入口不经过 ThinkPHP 路由，因此无需注册路由或执行路由发现命令。

TP6、TP7、TP8 默认加载以下启动文件之一：

```text
app/Base.php
app/base.php
```

若项目使用自定义启动文件，请设置环境变量：

```text
IOTMONITOR_BOOTSTRAP=/absolute/path/to/bootstrap.php
```

## 辅助方法

```php
use CreatCode\ThinkIotMonitor\ManagerHelper;

// 读取配置，支持点号路径。
ManagerHelper::config('traffic.flush_interval', 5);
ManagerHelper::boolConfig('traffic.enable', false);
ManagerHelper::dbConfig('redis_ping_interval', 15);
ManagerHelper::pluginConfig();

// 状态判断。
ManagerHelper::pluginEnabled();
ManagerHelper::trafficEnabled();

// 工具方法。
ManagerHelper::deviceActiveTimeKey();
ManagerHelper::log('monitor.log', 'message');
```

## 卸载说明

执行 Composer 卸载时，组件会删除其发布到项目中的配置文件、`public/iotmonitor.php` 与 `public/iotmonitor-assets/`。

卸载前请先备份已修改的监控配置、入口或静态资源。

## 安全建议

监控页面会展示运行状态、队列和设备统计信息。生产环境应通过 Web 服务器访问控制、IP 白名单或认证机制限制以下入口：

```text
/iotmonitor.php
/iotmonitor.php/overview
```

## 许可证

MIT
