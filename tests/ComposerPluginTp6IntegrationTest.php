<?php

$packageRoot = dirname(__DIR__);
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-iotmonitor-composer-tp6-' . uniqid('', true);

try {
    foreach (array('framework', 'workerman') as $package) {
        mkdir($root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $package, 0777, true);
    }

    file_put_contents($root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'name' => 'topthink/framework',
        'version' => '6.1.0',
    )));
    file_put_contents($root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'workerman' . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'name' => 'workerman/workerman',
        'version' => '4.1.0',
    )));

    // 使用本地路径仓库，验证首次 Composer 安装时插件能够发布 TP6 配置。
    file_put_contents($root . DIRECTORY_SEPARATOR . 'composer.json', json_encode(array(
        'name' => 'test/tp6-project',
        'repositories' => array(
            array('type' => 'path', 'url' => str_replace('\\', '/', $packageRoot), 'options' => array('symlink' => false)),
            array('type' => 'path', 'url' => 'packages/framework', 'options' => array('symlink' => false)),
            array('type' => 'path', 'url' => 'packages/workerman', 'options' => array('symlink' => false)),
        ),
        'require' => array(
            'creatcode/think-iotmonitor' => '*@dev',
            'topthink/framework' => '^6.0',
        ),
        'minimum-stability' => 'dev',
        'config' => array(
            'allow-plugins' => array('creatcode/think-iotmonitor' => true),
            'audit' => array('block-insecure' => false),
        ),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    mkdir($root . DIRECTORY_SEPARATOR . 'config', 0777, true);

    $command = 'composer update --no-interaction --no-progress --working-dir=' . escapeshellarg($root) . ' 2>&1';
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Composer plugin installation failed:\n" . implode("\n", $output));
    }

    $configFile = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'iotmonitor.php';
    if (!is_file($configFile)) {
        throw new RuntimeException("Composer plugin did not publish TP6 configuration:\n" . implode("\n", $output));
    }

    echo "Composer TP6 plugin integration test passed\n";
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
