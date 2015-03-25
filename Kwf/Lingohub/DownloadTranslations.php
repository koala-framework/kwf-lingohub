<?php
namespace Kwf\Lingohub;
use Psr\Log\LoggerInterface;
use Kwf\Lingohub\Config\ConfigInterface;

class DownloadTranslations
{
    protected $_logger;
    protected $_config;
    public function __construct(LoggerInterface $logger, ConfigInterface $config)
    {
        $this->_logger = $logger;
        $this->_config = $config;
    }

    private function _getComposerJsonFiles()
    {
        $files = glob('vendor/*/*/composer.json');
        array_unshift($files, 'composer.json');
        return $files;
    }

    public function downloadTrlFiles()
    {
        $this->_logger->info('Iterating over packages and downloading trl-resources');
        $composerJsonFilePaths = $this->_getComposerJsonFiles();
        foreach ($composerJsonFilePaths as $composerJsonFilePath) {
            $composerJsonFile = file_get_contents($composerJsonFilePath);
            $composerConfig = json_decode($composerJsonFile);

            if (!isset($composerConfig->extra->{'kwf-lingohub'})) continue;

            $kwfLingohub = $composerConfig->extra->{'kwf-lingohub'};
            $this->_logger->info("Checking for resources of {$kwfLingohub->account}/{$kwfLingohub->project}");

            $params = array( 'auth_token' => $this->_config->getApiToken() );
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
                file_put_contents($trlDir.'/'.$resource->project_locale.'.po', $file);
            }
        }
    }
}
