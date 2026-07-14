<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use CreatCode\ThinkIotMonitor\Composer\ConfigPublisher;

$source = dirname(__DIR__) . '/src/config/iotmonitor.php';
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-iotmonitor-' . uniqid('', true);

try {
    mkdir($root, 0777, true);
    file_put_contents($root . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'require' => array('topthink/framework' => '~5.0.0'),
    )));
    mkdir($root . DIRECTORY_SEPARATOR . 'application', 0777, true);
    mkdir($root . DIRECTORY_SEPARATOR . 'public', 0777, true);
    file_put_contents($root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'tags.php', "<?php\n\nreturn [\n    'app_init' => [\n        'app\\\\common\\\\behavior\\\\Common',\n    ],\n];\n");
    $tp5Path = ConfigPublisher::publish($root, $source);

    if ($tp5Path !== $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'iotmonitor.php' || !is_file($tp5Path)) {
        throw new RuntimeException('ThinkPHP 5 configuration was not published to application/extra.');
    }
    $assets = ConfigPublisher::publishAssets($root, dirname(__DIR__) . '/src/Http/resources/assets');
    if ($assets !== $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'iotmonitor-assets' || !is_file($assets . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'echarts.min.js')) {
        throw new RuntimeException('Monitor static assets were not published to public/iotmonitor-assets.');
    }

    file_put_contents($tp5Path, '<?php return array("custom" => true);');
    ConfigPublisher::publish($root, $source);
    if (strpos(file_get_contents($tp5Path), 'custom') === false) {
        throw new RuntimeException('Existing ThinkPHP 5 configuration was overwritten.');
    }

    $entryPath = ConfigPublisher::publishHttpEntry($root);
    if ($entryPath !== $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'iotmonitor.php' || !is_file($entryPath) || strpos((string) file_get_contents($entryPath), 'MonitorController') === false) {
        throw new RuntimeException('ThinkPHP 5 monitor entry was not published to public/iotmonitor.php.');
    }

    $devMasterRoot = $root . DIRECTORY_SEPARATOR . 'dev-master';
    mkdir($devMasterRoot . DIRECTORY_SEPARATOR . 'application', 0777, true);
    file_put_contents($devMasterRoot . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'require' => array('topthink/framework' => 'dev-master'),
    )));
    file_put_contents($devMasterRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'tags.php', "<?php\nreturn ['app_init' => []];\n");
    mkdir($devMasterRoot . DIRECTORY_SEPARATOR . 'public', 0777, true);
    $devMasterEntry = ConfigPublisher::publishHttpEntry($devMasterRoot);
    if ($devMasterEntry === null || !is_file($devMasterEntry)) {
        throw new RuntimeException('ThinkPHP 5 dev-master monitor entry fallback did not run.');
    }

    $tp51Root = $root . DIRECTORY_SEPARATOR . 'tp51';
    mkdir($tp51Root, 0777, true);
    file_put_contents($tp51Root . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'require' => array('topthink/framework' => '5.1.*'),
        'extra' => array('app-path' => 'app'),
    )));
    mkdir($tp51Root . DIRECTORY_SEPARATOR . 'app', 0777, true);
    $tp51Path = ConfigPublisher::publish($tp51Root, $source);
    if ($tp51Path !== $tp51Root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'iotmonitor.php' || !is_file($tp51Path)) {
        throw new RuntimeException('ThinkPHP 5.1 configuration was not published to the configured app-path.');
    }

    $tp6Root = $root . DIRECTORY_SEPARATOR . 'tp6';
    mkdir($tp6Root, 0777, true);
    file_put_contents($tp6Root . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'require' => array('topthink/framework' => '^6.0'),
    )));
    mkdir($tp6Root . DIRECTORY_SEPARATOR . 'config', 0777, true);
    $tp6Path = ConfigPublisher::publish($tp6Root, $source);
    if ($tp6Path !== $tp6Root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'iotmonitor.php' || !is_file($tp6Path)) {
        throw new RuntimeException('ThinkPHP 6+ configuration was not published to config.');
    }
    mkdir($tp6Root . DIRECTORY_SEPARATOR . 'public', 0777, true);
    $tp6Entry = ConfigPublisher::publishHttpEntry($tp6Root);
    if ($tp6Entry === null || strpos((string) file_get_contents($tp6Entry), "vendor/autoload.php") === false) {
        throw new RuntimeException('ThinkPHP 6+ monitor entry was not published.');
    }

    $conflictRoot = $root . DIRECTORY_SEPARATOR . 'conflict';
    mkdir($conflictRoot, 0777, true);
    file_put_contents($conflictRoot . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'require' => array('topthink/framework' => '^5.1'),
    )));
    mkdir($conflictRoot . DIRECTORY_SEPARATOR . 'application', 0777, true);
    mkdir($conflictRoot . DIRECTORY_SEPARATOR . 'config', 0777, true);
    $conflictPath = ConfigPublisher::publish($conflictRoot, $source);
    if ($conflictPath !== $conflictRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'iotmonitor.php' || !is_file($conflictPath)) {
        throw new RuntimeException('ThinkPHP 5 configuration was misidentified when both directories exist.');
    }

    $installedRoot = $root . DIRECTORY_SEPARATOR . 'installed';
    mkdir($installedRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer', 0777, true);
    file_put_contents($installedRoot . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'require' => array('topthink/framework' => '^5.1 || ^6.0'),
    )));
    file_put_contents($installedRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json', json_encode(array(
        'packages' => array(array('name' => 'topthink/framework', 'version' => '6.1.0')),
    )));
    mkdir($installedRoot . DIRECTORY_SEPARATOR . 'application', 0777, true);
    mkdir($installedRoot . DIRECTORY_SEPARATOR . 'config', 0777, true);
    $installedPath = ConfigPublisher::publish($installedRoot, $source);
    if ($installedPath !== $installedRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'iotmonitor.php' || !is_file($installedPath)) {
        throw new RuntimeException('Installed ThinkPHP version was not used as the fallback publisher source.');
    }

    $cleanupRoot = $root . DIRECTORY_SEPARATOR . 'cleanup';
    mkdir($cleanupRoot . DIRECTORY_SEPARATOR . 'application', 0777, true);
    mkdir($cleanupRoot . DIRECTORY_SEPARATOR . 'public', 0777, true);
    file_put_contents($cleanupRoot . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'require' => array('topthink/framework' => '^5.1'),
    )));
    $cleanupConfig = ConfigPublisher::publish($cleanupRoot, $source);
    $cleanupAssets = ConfigPublisher::publishAssets($cleanupRoot, dirname(__DIR__) . '/src/Http/resources/assets');
    $cleanupEntry = ConfigPublisher::publishHttpEntry($cleanupRoot);
    file_put_contents($cleanupConfig, '<?php return array("modified" => true);');
    file_put_contents($cleanupAssets . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'echarts.min.js', 'modified');
    file_put_contents($cleanupEntry, '<?php echo "modified";');
    ConfigPublisher::unpublish($cleanupRoot);
    if (is_file($cleanupConfig) || is_file($cleanupEntry) || is_dir($cleanupAssets)) {
        throw new RuntimeException('Published monitor files were not removed during uninstall.');
    }

    echo "Config publisher test passed\n";
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }
}
