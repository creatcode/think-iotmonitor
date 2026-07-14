<?php

namespace CreatCode\ThinkIotMonitor\Composer;

class ConfigPublisher
{
    /** 发布 ThinkPHP 配置模板，已有配置文件不覆盖。 */
    public static function publish(string $projectRoot, string $sourceFile): ?string
    {
        if (!is_file($sourceFile)) {
            throw new \RuntimeException('Think IoT Monitor configuration template is missing: ' . $sourceFile);
        }

        $target = self::targetPath(rtrim($projectRoot, '/\\'));
        if ($target === null) {
            return null;
        }

        if (is_file($target)) {
            return $target;
        }

        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create ThinkPHP configuration directory: ' . $directory);
        }

        if (!copy($sourceFile, $target)) {
            throw new \RuntimeException('Unable to publish Think IoT Monitor configuration: ' . $target);
        }

        return $target;
    }

    /** 发布包内静态资源到项目 public 目录。 */
    public static function publishAssets(string $projectRoot, string $sourceDirectory): ?string
    {
        $target = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'iotmonitor-assets';
        if (!is_dir($sourceDirectory) || !is_dir(dirname($target))) {
            return null;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen(rtrim($sourceDirectory, '/\\')) + 1);
            $destination = $target . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
                    throw new \RuntimeException('Unable to create asset directory: ' . $destination);
                }
                continue;
            }

            $directory = dirname($destination);
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create asset directory: ' . $directory);
            }
            if (!copy($item->getPathname(), $destination)) {
                throw new \RuntimeException('Unable to publish monitor asset: ' . $destination);
            }
        }

        return $target;
    }

    /** 发布无路由依赖的独立监控入口。 */
    public static function publishHttpEntry(string $projectRoot): ?string
    {
        $projectRoot = rtrim($projectRoot, '/\\');
        $package = self::rootPackage($projectRoot);
        $appPath = self::appPath($package);
        $tagsFile = $projectRoot . DIRECTORY_SEPARATOR . $appPath . DIRECTORY_SEPARATOR . 'tags.php';
        $isTp5 = self::isTp5($projectRoot, $package, $tagsFile);
        $frameworkMajor = self::frameworkMajor($projectRoot, $package);
        if (!$isTp5 && ($frameworkMajor === null || $frameworkMajor < 6)) {
            return null;
        }

        $publicDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'public';
        if (!is_dir($publicDirectory)) {
            return null;
        }

        $target = $publicDirectory . DIRECTORY_SEPARATOR . 'iotmonitor.php';
        if (is_file($target)) {
            return $target;
        }

        $entry = $isTp5
            ? self::tp5HttpEntry(str_replace('\\', '/', $appPath))
            : self::tp6HttpEntry();
        if (file_put_contents($target, $entry) === false) {
            throw new \RuntimeException('Unable to publish ThinkPHP monitor entry: ' . $target);
        }

        return $target;
    }

    /**
     * 删除本包发布到 ThinkPHP 项目的全部文件。
     *
     * 卸载时不校验文件内容；即使发布后的配置、入口或静态资源被修改，
     * 也会一并删除，避免项目保留无法使用的监控文件。
     */
    public static function unpublish(string $projectRoot): array
    {
        $projectRoot = rtrim($projectRoot, '/\\');
        $package = self::rootPackage($projectRoot);
        $appPath = self::appPath($package);
        $configPaths = array_filter(array_unique(array(
            self::targetPath($projectRoot),
            $projectRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'iotmonitor.php',
            $projectRoot . DIRECTORY_SEPARATOR . $appPath . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'iotmonitor.php',
            $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'iotmonitor.php',
        )));
        $removed = array();

        foreach ($configPaths as $configPath) {
            if (self::removeFile($configPath)) {
                $removed[] = $configPath;
            }
        }

        $entry = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'iotmonitor.php';
        if (self::removeFile($entry)) {
            $removed[] = $entry;
        }

        $assets = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'iotmonitor-assets';
        if (self::removeDirectory($assets)) {
            $removed[] = $assets;
        }

        return $removed;
    }

    /** 生成 TP5 独立入口。 */
    private static function tp5HttpEntry(string $appPath): string
    {
        $entry = <<<'PHP'
<?php

// Think IoT Monitor 独立入口，不依赖 ThinkPHP 路由。
define('APP_PATH', __DIR__ . '/../__IOTMONITOR_APP_PATH__/');
require __DIR__ . '/../thinkphp/base.php';

\think\App::initCommon();
$controller = new \CreatCode\ThinkIotMonitor\Http\MonitorController();
$path = trim(isset($_SERVER['PATH_INFO']) ? (string) $_SERVER['PATH_INFO'] : '', '/');
$response = $path === 'overview' ? $controller->overview() : $controller->index();

if ($response instanceof \think\Response) {
    $response->send();
} else {
    echo $response;
}
PHP;

        return str_replace('__IOTMONITOR_APP_PATH__', $appPath, $entry) . "\n";
    }

    /** 生成 TP6 至 TP8 独立入口。 */
    private static function tp6HttpEntry(): string
    {
        return <<<'PHP'
<?php

// Think IoT Monitor 独立入口，不依赖 ThinkPHP 路由。
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$bootstrap = getenv('IOTMONITOR_BOOTSTRAP');
if ($bootstrap === false || $bootstrap === '') {
    foreach (array($root . '/app/Base.php', $root . '/app/base.php') as $candidate) {
        if (is_file($candidate)) {
            $bootstrap = $candidate;
            break;
        }
    }
}

if (!is_string($bootstrap) || !is_file($bootstrap)) {
    http_response_code(500);
    echo 'Think IoT Monitor bootstrap file was not found.';
    exit;
}

require $bootstrap;
$controller = new \CreatCode\ThinkIotMonitor\Http\MonitorController();
$path = trim(isset($_SERVER['PATH_INFO']) ? (string) $_SERVER['PATH_INFO'] : '', '/');
$response = $path === 'overview' ? $controller->overview() : $controller->index();

if ($response instanceof \think\Response) {
    $response->send();
} else {
    echo $response;
}
PHP;
    }

    /** 根据 ThinkPHP 版本确定配置文件位置。 */
    private static function targetPath(string $projectRoot): ?string
    {
        $package = self::rootPackage($projectRoot);
        $frameworkMajor = self::frameworkMajor($projectRoot, $package);
        $appPath = self::appPath($package);
        $tagsFile = $projectRoot . DIRECTORY_SEPARATOR . $appPath . DIRECTORY_SEPARATOR . 'tags.php';

        if (self::isTp5($projectRoot, $package, $tagsFile)) {
            $applicationDirectory = $projectRoot . DIRECTORY_SEPARATOR . $appPath;
            return is_dir($applicationDirectory)
                ? $applicationDirectory . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'iotmonitor.php'
                : null;
        }

        if ($frameworkMajor !== null && $frameworkMajor >= 6) {
            $configDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'config';
            return is_dir($configDirectory) ? $configDirectory . DIRECTORY_SEPARATOR . 'iotmonitor.php' : null;
        }

        $configDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'config';
        $applicationDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'application';
        if (is_dir($configDirectory) && !is_dir($applicationDirectory)) {
            return $configDirectory . DIRECTORY_SEPARATOR . 'iotmonitor.php';
        }
        if (is_dir($applicationDirectory) && !is_dir($configDirectory)) {
            return $applicationDirectory . DIRECTORY_SEPARATOR . 'extra' . DIRECTORY_SEPARATOR . 'iotmonitor.php';
        }

        return null;
    }

    /** 删除单个发布文件。 */
    private static function removeFile(string $file): bool
    {
        if (!is_file($file) && !is_link($file)) {
            return false;
        }

        if (!unlink($file)) {
            throw new \RuntimeException('Unable to remove Think IoT Monitor published file: ' . $file);
        }

        return true;
    }

    /** 递归删除发布的静态资源目录。 */
    private static function removeDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                if (!rmdir($item->getPathname())) {
                    throw new \RuntimeException('Unable to remove Think IoT Monitor asset directory: ' . $item->getPathname());
                }
                continue;
            }

            if (!unlink($item->getPathname())) {
                throw new \RuntimeException('Unable to remove Think IoT Monitor asset file: ' . $item->getPathname());
            }
        }

        if (!rmdir($directory)) {
            throw new \RuntimeException('Unable to remove Think IoT Monitor asset directory: ' . $directory);
        }

        return true;
    }

    /** 判断项目是否为 TP5 或 TP5.1。 */
    private static function isTp5(string $projectRoot, array $package, string $tagsFile): bool
    {
        $frameworkMajor = self::frameworkMajor($projectRoot, $package);
        return $frameworkMajor === 5 || ($frameworkMajor === null && is_file($tagsFile));
    }

    /** 获取 TP5 应用目录。 */
    private static function appPath(array $package): string
    {
        if (isset($package['extra']['app-path']) && is_string($package['extra']['app-path'])) {
            return trim($package['extra']['app-path'], '/\\');
        }

        return 'application';
    }

    /** 读取根项目 composer.json。 */
    private static function rootPackage(string $projectRoot): array
    {
        return self::readJsonFile($projectRoot . DIRECTORY_SEPARATOR . 'composer.json');
    }

    /** 依次使用锁定版本、安装版本和根依赖识别 ThinkPHP 主版本。 */
    private static function frameworkMajor(string $projectRoot, array $package): ?int
    {
        foreach (self::lockedFrameworkVersions($projectRoot) as $version) {
            $major = self::majorFromVersion($version);
            if ($major !== null) {
                return $major;
            }
        }

        return isset($package['require']['topthink/framework']) && is_string($package['require']['topthink/framework'])
            ? self::majorFromConstraint($package['require']['topthink/framework'])
            : null;
    }

    /** 读取 Composer 锁文件和安装清单中的框架版本。 */
    private static function lockedFrameworkVersions(string $projectRoot): array
    {
        $versions = array();
        $lock = self::readJsonFile($projectRoot . DIRECTORY_SEPARATOR . 'composer.lock');
        foreach (array('packages', 'packages-dev') as $section) {
            foreach (($lock[$section] ?? array()) as $lockedPackage) {
                if (is_array($lockedPackage) && ($lockedPackage['name'] ?? null) === 'topthink/framework' && isset($lockedPackage['version'])) {
                    $versions[] = (string) $lockedPackage['version'];
                }
            }
        }

        $installed = self::readJsonFile($projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json');
        $installedPackages = isset($installed['packages']) && is_array($installed['packages']) ? $installed['packages'] : $installed;
        foreach (is_array($installedPackages) ? $installedPackages : array() as $installedPackage) {
            if (is_array($installedPackage) && ($installedPackage['name'] ?? null) === 'topthink/framework' && isset($installedPackage['version'])) {
                $versions[] = (string) $installedPackage['version'];
            }
        }

        return array_values(array_unique($versions));
    }

    private static function majorFromVersion(string $version): ?int
    {
        return preg_match('/(?:^|[^\\d])([5-8])(?:\\.\\d+){1,3}(?:$|[^\\d])/', $version, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    private static function majorFromConstraint(string $constraint): ?int
    {
        preg_match_all('/(?<!\\d)([5-8])(?:\\.\\d+)?/', $constraint, $matches);
        $versions = array_unique($matches[1]);
        return count($versions) === 1 ? (int) reset($versions) : null;
    }

    private static function readJsonFile(string $file): array
    {
        if (!is_file($file)) {
            return array();
        }

        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : array();
    }
}
