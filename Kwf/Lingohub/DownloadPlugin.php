<?php
namespace Kwf\Lingohub;

use Composer\Composer;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Kwf\Lingohub\Output;

class DownloadPlugin implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;

        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'post-install-cmd' => array(
                array('onPostUpdateInstall', 0)
            ),
            'post-update-cmd' => array(
                array('onPostUpdateInstall', 0)
            ),
        );
    }

    public function onPostUpdateInstall(Event $event)
    {
        $download = new DownloadTranslations(new ComposerOutput($output));
        $download->downloadTrlFiles();
    }
}
