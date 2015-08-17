<?php
namespace Kwf\Lingohub;
use Psr\Log\LoggerInterface;
use Kwf\Lingohub\Config\ConfigInterface;

class DownloadTranslations
{
    protected $_logger;
    protected $_config;
    protected $_updateDownloadedTrlFiles = false;

    public function __construct(LoggerInterface $logger, ConfigInterface $config)
    {
        $this->_logger = $logger;
        $this->_config = $config;
    }

    public static function getComposerJsonFiles()
    {
        $files = glob('vendor/*/*/composer.json');
        array_unshift($files, 'composer.json');
        return $files;
    }

    public function setUpdateDownloadedTrlFiles($updateDownloadedTrlFiles)
    {
        $this->_updateDownloadedTrlFiles = $updateDownloadedTrlFiles;
    }

    public function downloadTrlFiles()
    {
        $this->_logger->info('Iterating over packages and downloading trl-resources');
        $composerJsonFilePaths = DownloadTranslations::getComposerJsonFiles();
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
                $trlDir = dirname($composerJsonFilePath).'/trl';
                $poFilePath = $trlDir.'/'.$resource->project_locale.'.po';
                if (!$this->_updateDownloadedTrlFiles && file_exists($poFilePath)) {
                    continue;
                }
                $this->_logger->info("Downloading {$resource->name}");
                $urlParts = parse_url($resource->links[0]->href);
                $separator =  isset($urlParts['query']) ? '&' : '?';
                $file = file_get_contents($resource->links[0]->href.$separator.http_build_query($params));
                if (!file_exists($trlDir)) {
                    mkdir($trlDir);
                }
                file_put_contents($poFilePath, $file);
            }
        }
    }
}
