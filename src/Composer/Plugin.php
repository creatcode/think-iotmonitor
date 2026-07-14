<?php

namespace CreatCode\IotMonitor\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /** @var Composer */
    private $composer;

    /** @var IOInterface */
    private $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        $vendorDirectory = $composer->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDirectory);
        $removed = ConfigPublisher::unpublish($projectRoot);

        if (!empty($removed)) {
            $io->write('<info>Removed Think IoT Monitor published files: ' . implode(', ', $removed) . '</info>');
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            PackageEvents::POST_PACKAGE_INSTALL => 'onPackageInstall',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPackageUpdate',
            PackageEvents::PRE_PACKAGE_UNINSTALL => 'onPackageUninstall',
        );
    }

    public function onPackageInstall(PackageEvent $event)
    {
        $operation = $event->getOperation();
        $this->publishPackageConfig($operation->getPackage());
    }

    public function onPackageUpdate(PackageEvent $event)
    {
        $operation = $event->getOperation();
        $this->publishPackageConfig($operation->getTargetPackage());
    }

    /** 在 Composer 删除包文件前移除本包发布的项目资源。 */
    public function onPackageUninstall(PackageEvent $event)
    {
        $operation = $event->getOperation();
        $this->unpublishPackageFiles($operation->getPackage());
    }

    /**
     * 仅在本包安装或更新时发布配置。
     */
    private function publishPackageConfig(PackageInterface $package)
    {
        if ($package->getName() !== 'creatcode/think-iotmonitor') {
            return;
        }

        $installPath = $this->composer->getInstallationManager()->getInstallPath($package);
        $sourceFile = $installPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'iotmonitor.php';
        $vendorDirectory = $this->composer->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDirectory);
        $target = ConfigPublisher::publish($projectRoot, $sourceFile);
        $assets = ConfigPublisher::publishAssets($projectRoot, $installPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'assets');
        $removedTags = ConfigPublisher::removeTp5RouteBehavior($projectRoot);
        $entry = ConfigPublisher::publishHttpEntry($projectRoot);

        if ($target !== null) {
            $this->io->write('<info>Published Think IoT Monitor configuration: ' . $target . '</info>');
        }
        if ($assets !== null) {
            $this->io->write('<info>Published Think IoT Monitor assets: ' . $assets . '</info>');
        }
        if ($removedTags !== null) {
            $this->io->write('<info>Removed obsolete Think IoT Monitor route behavior from: ' . $removedTags . '</info>');
        }
        if ($entry !== null) {
            $this->io->write('<info>Published Think IoT Monitor entry: ' . $entry . '</info>');
        }
    }

    /** 删除包曾发布到项目中的配置、入口与静态资源。 */
    private function unpublishPackageFiles(PackageInterface $package)
    {
        if ($package->getName() !== 'creatcode/think-iotmonitor') {
            return;
        }

        $vendorDirectory = $this->composer->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDirectory);
        $removed = ConfigPublisher::unpublish($projectRoot);

        if (!empty($removed)) {
            $this->io->write('<info>Removed Think IoT Monitor published files: ' . implode(', ', $removed) . '</info>');
        }
    }
}
