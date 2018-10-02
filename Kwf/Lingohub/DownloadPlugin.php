<?php
namespace Kwf\Lingohub;

use Composer\Composer;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Kwf\Lingohub\Output\ComposerOutput;
use Kwf\Lingohub\Config\Config;

class DownloadPlugin implements PluginInterface, EventSubscriberInterface
{
    protected $_composer;
    protected $_io;
    protected $_config;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->_composer = $composer;
        $this->_io = $io;
        $this->_config = new Config();
    }

    public static function getSubscribedEvents()
    {
        return array(
            'post-install-cmd' => array(
                array('onPostInstallUpdate', 0)
            ),
            'post-update-cmd' => array(
                array('onPostInstallUpdate', 0)
            )
        );
    }

    public function onPostInstallUpdate(Event $event)
    {
        $skipDownloadTranslations = isset($_SERVER['SKIP_DOWNLOAD_TRANSLATIONS']) && filter_var($_SERVER['SKIP_DOWNLOAD_TRANSLATIONS'], FILTER_VALIDATE_BOOLEAN);
        if (!$skipDownloadTranslations) $this->_downloadTranslations($event);
    }

    private function _downloadTranslations(Event $event)
    {
        if (!class_exists(__NAMESPACE__.'\\DownloadTranslations')) { // uninstalling this package
            return;
        }
        $download = new DownloadTranslations(new ComposerOutput($this->_io), $this->_config);
        try {
            $download->downloadTrlFiles();
        } catch (LingohubException $e) {
            echo "LingohubException: ".$e->getMessage()."\n";
        }
    }
}
