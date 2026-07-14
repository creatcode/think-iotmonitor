# creatcode/think-iotmonitor

面向 ThinkPHP 与 Workerman TCP 服务的 IoT 监控组件。提供协议辅助、Redis 与数据库连接管理、
TCP 流量统计、设备和队列总览，以及内置监控页面。

## 特性

- 兼容 ThinkPHP 5.0、5.1、6、7、8 及 FastAdmin 的 `topthink/framework: dev-master`。
- 支持 Workerman 4、5 的协议流量和上报包统计。
- 内置 Modbus TCP、Modbus RTU、LoRa、温度协议能力。
- 自动发布配置、静态资源和独立监控入口。
- 不注册 ThinkPHP 路由；项目关闭路由、绑定模块或使用自定义后台入口时仍可访问。

## 安装

```bash
composer require creatcode/think-iotmonitor
```

Composer 2.2+ 需要在项目 `composer.json` 中允许插件：

```json
{
  "config": {
    "allow-plugins": {
      "creatcode/think-iotmonitor": true
    }
  }
}
```

安装或更新后执行：

```bash
composer update creatcode/think-iotmonitor
```

## 自动发布内容

| 环境 | 配置文件 |
| --- | --- |
| TP5 / TP5.1 | `{app-path}/extra/iotmonitor.php`，默认 `application/extra/iotmonitor.php` |
| TP6 / TP7 / TP8 | `config/iotmonitor.php` |

同时会发布：

```text
public/iotmonitor.php
public/iotmonitor-assets/
```

已有同名配置、入口或资源不会被覆盖。旧版本若曾向 TP5 `tags.php` 写入监控路由行为，升级时会自动移除。

## 监控页面

所有 ThinkPHP 版本统一通过独立入口访问：

```text
/iotmonitor.php
```

数据接口：

```text
/iotmonitor.php/overview?minutes=60
```

支持的统计窗口为 `5`、`60`、`360`、`1440` 分钟。入口不经过框架路由，因此不需要执行服务发现命令。

TP6–TP8 默认使用项目的 `app/Base.php` 或 `app/base.php` 初始化运行环境。若项目使用自定义启动文件，
请设置环境变量 `IOTMONITOR_BOOTSTRAP` 为该文件的绝对路径。

## Workerman 启动

在每个 TCP Worker 的 `onWorkerStart` 中调用一次：

```php
use CreatCode\IotMonitor\Monitor\WorkermanMonitorBootstrap;
use CreatCode\IotMonitor\Runtime\Runtime;

$worker->onWorkerStart = function () {
    Runtime::boot();
    WorkermanMonitorBootstrap::boot();
};
```

## 注意事项

- Web 服务需允许访问 `public/iotmonitor.php`，并允许读取 `public/iotmonitor-assets`。
- 生产环境应限制监控入口的网络访问或在 Web 服务器层增加认证。
- 已有 `iotmonitor.php` 不会被自动覆盖；需要更新入口时请先备份并手动替换。
