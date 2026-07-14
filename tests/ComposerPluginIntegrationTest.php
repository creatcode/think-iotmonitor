<?php

$packageRoot = dirname(__DIR__);
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-iotmonitor-composer-' . uniqid('', true);

try {
    foreach (array('framework', 'workerman') as $package) {
        mkdir($root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $package, 0777, true);
    }

    file_put_contents($root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'name' => 'topthink/framework',
        'version' => '5.1.41',
    )));
    file_put_contents($root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'workerman' . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'name' => 'workerman/workerman',
        'version' => '4.1.0',
    )));

    // 使用本地路径仓库，验证首次 Composer 安装时插件能够发布 TP5.1 配置。
    file_put_contents($root . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'name' => 'test/tp51-project',
        'repositories' => array(
            array('type' => 'path', 'url' => str_replace('\\', '/', $packageRoot), 'options' => array('symlink' => false)),
            array('type' => 'path', 'url' => 'packages/framework', 'options' => array('symlink' => false)),
            array('type' => 'path', 'url' => 'packages/workerman', 'options' => array('symlink' => false)),
        ),
        'require' => array(
            'creatcode/think-iotmonitor' => '*@dev',
            'topthink/framework' => '^5.1 || ^6.0',
        ),
        'minimum-stability' => 'dev',
        'config' => array(
            'allow-plugins' => array('creatcode/think-iotmonitor' => true),
            'audit' => array('block-insecure' => false),
        ),
        'extra' => array('app-path' => 'app'),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    mkdir($root . DIRECTORY_SEPARATOR . 'app', 0777, true);
    mkdir($root . DIRECTORY_SEPARATOR . 'config', 0777, true);
    mkdir($root . DIRECTORY_SEPARATOR . 'public', 0777, true);

    $command = 'composer update --no-interaction --no-progress --working-dir=' . escapeshellarg($root) . ' 2>&1';
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Composer plugin installation failed:\n" . implode("\n", $output));
    }

    $configFile = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'iotmonitor.php';
    if (!is_file($configFile)) {
        throw new RuntimeException("Composer plugin did not publish TP5.1 configuration:\n" . implode("\n", $output));
    }

    $entryFile = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'iotmonitor.php';
    $assetsDirectory = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'iotmonitor-assets';
    if (!is_file($entryFile) || !is_dir($assetsDirectory)) {
        throw new RuntimeException("Composer plugin did not publish monitor HTTP resources:\n" . implode("\n", $output));
    }

    // 卸载时即使用户修改过发布文件，也必须清理全部包资源。
    file_put_contents($configFile, '<?php return array("modified" => true);');
    file_put_contents($entryFile, '<?php echo "modified";');
    file_put_contents($assetsDirectory . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'echarts.min.js', 'modified');
    $command = 'composer remove creatcode/think-iotmonitor --no-interaction --no-progress --working-dir=' . escapeshellarg($root) . ' 2>&1';
    $removeOutput = array();
    exec($command, $removeOutput, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Composer plugin uninstallation failed:\n" . implode("\n", $removeOutput));
    }
    clearstatcache();
    $remaining = array_filter(array(
        'config' => is_file($configFile),
        'entry' => is_file($entryFile),
        'assets' => is_dir($assetsDirectory),
    ));
    if (!empty($remaining)) {
        throw new RuntimeException("Composer plugin did not remove published monitor files (remaining: " . implode(', ', array_keys($remaining)) . "):\n" . implode("\n", $removeOutput));
    }

    echo "Composer plugin integration test passed\n";
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
