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

    public function downloadTrlFiles()
    {
        $config = json_decode(file_get_contents($_SERVER['HOME'].'/.config/koala-framework/kwf-lingohub/config'));
        $apiToken = $config->apiToken;
        if (!$apiToken) $this->_logger->critical('No API-Token found in "~/.config/koala-framework/kwf-lingohub/api-token"! Cannot load resources without Api-Token!');

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
