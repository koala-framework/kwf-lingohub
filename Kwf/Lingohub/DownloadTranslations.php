<?php
namespace Kwf\Lingohub;
use Psr\Log\LoggerInterface;

class DownloadTranslations
{
    protected $_logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->_logger = $logger;
    }

    private function _getComposerJsonFiles()
    {
        $files = glob('vendor/*/*/composer.json');
        array_unshift($files, 'composer.json');
        return $files;
    }

    /**
    * Adopted from https://github.com/composer/composer/blob/9f9cff558e5f447165f4265f320b2b1178f18301/src/Composer/Factory.php
    */
    private function _getHomeDir()
    {
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            if (!getenv('APPDATA')) {
                $this->_logger->critical("The APPDATA environment variable must be set for kwf-lingohub to run correctly");
                exit(1);
            }
            $home = strtr(getenv('APPDATA'), '\\', '/') . '/Config';
        } else {
            if (!getenv('HOME')) {
                $this->_logger->critical("The HOME environment variable must be set for kwf-lingohub to run correctly");
                exit(1);
            }
            $home = rtrim(getenv('HOME'), '/') . '/.config';
        }
        return $home;
    }

    public function downloadTrlFiles()
    {
        $path = $this->_getHomeDir().'/koala-framework/kwf-lingohub/config';
        if (!file_exists($path)) {
            $this->_logger->critical("No kwf-lingohub config found! ($path)");
            exit(1);
        }
        $config = json_decode(file_get_contents($path));
        if (!isset($config->apiToken)) {
            $this->_logger->critical("No API-Token found in $path! Cannot load resources without Api-Token!");
            exit(1);
        }
        $apiToken = $config->apiToken;

        $this->_logger->info('Iterating over packages and downloading trl-resources');
        $composerJsonFilePaths = $this->_getComposerJsonFiles();
        foreach ($composerJsonFilePaths as $composerJsonFilePath) {
            $composerJsonFile = file_get_contents($composerJsonFilePath);
            $composerConfig = json_decode($composerJsonFile);

            if (!isset($composerConfig->extra->{'kwf-lingohub'})) continue;

            $kwfLingohub = $composerConfig->extra->{'kwf-lingohub'};
            $this->_logger->info("Checking for resources of {$kwfLingohub->account}/{$kwfLingohub->project}");

            $params = array( 'auth_token' => $apiToken );
            $resourcesUrl = "https://api.lingohub.com/v1/{$kwfLingohub->account}"
                ."/projects/{$kwfLingohub->project}/resources.json"
                ."?".http_build_query($params);
            $resources = json_decode(file_get_contents($resourcesUrl));
            foreach ($resources->members as $resource) {
                $this->_logger->info("Downloading {$resource->name}");
                $file = file_get_contents($resource->links[0]->href.'&'.http_build_query($params));
                $trlDir = dirname($composerJsonFilePath).'/trl';
                if (!file_exists($trlDir)) {
                    mkdir($trlDir);
                }
                file_put_contents($trlDir.'/'.$resource->name, $file);
            }
        }
    }
}
