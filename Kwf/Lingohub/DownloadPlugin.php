<?php
namespace Kwf\Lingohub;

use Composer\Composer;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

class Kwf_Lingohub_DownloadPlugin extends PluginInterface, EventSubscriberInterface
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
        $packages = array(
            $this->composer->getPackage()
        );
        $packages = array_merge($packages, $this->composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages());

        foreach ($packages as $package) {
            if (!($package instanceof \Composer\Package\CompletePackage)) continue;
            $extra = $package->getExtra();
            if (!isset($extra['kwf-lingohub'])) continue;

            $account = $extra['kwf-lingohub']['account'];
            $project = $extra['kwf-lingohub']['project'];
//             $filename = $extra['kwf-lingohub']['filename'];
//             $downloadUrl = "api/v1/$account/projects/$project/resources/$filename";
        }
    }
}
