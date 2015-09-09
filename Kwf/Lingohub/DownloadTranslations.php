<?php
namespace Kwf\Lingohub;
use Psr\Log\LoggerInterface;
use Kwf\Lingohub\Config\ConfigInterface;

class DownloadTranslations
{
    static $TEMP_TRL_FOLDER = 'trl';
    static $TEMP_LAST_UPDATE_FILE = 'last_update.txt';

    protected $_logger;
    protected $_config;
    protected $_updateDownloadedTrlFiles = false;

    public function __construct(LoggerInterface $logger, ConfigInterface $config)
    {
        $this->_logger = $logger;
        $this->_config = $config;
    }

    public function forceDownloadTrlFiles($download)
    {
        $this->_updateDownloadedTrlFiles = $download;
    }

    public static function getComposerJsonFiles()
    {
        $files = glob('vendor/*/*/composer.json');
        array_unshift($files, 'composer.json');
        return $files;
    }

    private function _getTempFolder($account = null, $project = null)
    {
        $path = sys_get_temp_dir().'/'.DownloadTranslations::$TEMP_TRL_FOLDER;
        if ($account && $project) {
            $path .= "/$account/$project";
        }
        return $path;
    }

    private function _getLastUpdateFile($account, $project)
    {
        return $this->_getTempFolder($account, $project).'/'.DownloadTranslations::$TEMP_LAST_UPDATE_FILE;
    }

    private function _checkDownloadTrlFiles($account, $project)
    {
        if ($this->_updateDownloadedTrlFiles) return true;
        $downloadFiles = true;
        if (file_exists($this->_getLastUpdateFile($account, $project))) {
            $lastDownloadTimestamp = strtotime(substr(file_get_contents('test.txt'), 0, strlen('HHHH-MM-DD')));
            $downloadFiles = strtotime('today') > $lastDownloadTimestamp;
        }
        return $downloadFiles;
    }

    public function downloadTrlFiles()
    {
        $start = microtime();
        $this->_logger->info('Iterating over packages and downloading trl-resources');
        $composerJsonFilePaths = DownloadTranslations::getComposerJsonFiles();
        foreach ($composerJsonFilePaths as $composerJsonFilePath) {
            $composerJsonFile = file_get_contents($composerJsonFilePath);
            $composerConfig = json_decode($composerJsonFile);

            if (!isset($composerConfig->extra->{'kwf-lingohub'})) continue;

            $kwfLingohub = $composerConfig->extra->{'kwf-lingohub'};
            $accountName = strtolower($kwfLingohub->account);
            $projectName = strtolower($kwfLingohub->project);
            if ($this->_checkDownloadTrlFiles($accountName, $projectName)) {
                $trlTempDir = $this->_getTempFolder($accountName, $projectName);
                if (!file_exists($trlTempDir)) {
                    mkdir($trlTempDir, 0666, true);//write and read for everyone
                }
                $this->_logger->info("Checking for resources of {$kwfLingohub->account}/{$kwfLingohub->project}");
                $params = array( 'auth_token' => $this->_config->getApiToken() );
                $resourcesUrl = "https://api.lingohub.com/v1/$accountName"
                    ."/projects/$projectName/resources.json"
                    ."?".http_build_query($params);
                $resources = json_decode(file_get_contents($resourcesUrl));
                foreach ($resources->members as $resource) {
                    $poFilePath = $trlTempDir.'/'.$resource->project_locale.'.po';
                    $this->_logger->info("Downloading {$resource->name}");
                    $urlParts = parse_url($resource->links[0]->href);
                    $separator =  isset($urlParts['query']) ? '&' : '?';
                    $file = file_get_contents($resource->links[0]->href.$separator.http_build_query($params));
                    file_put_contents($poFilePath, $file);
                }
                file_put_contents($this->_getLastUpdateFile($accountName, $projectName), date('Y-m-d H:i:s'));
            }
            copy($this->_getTempFolder($accountName, $projectName), dirname($composerJsonFilePath).'/trl');
        }
        $this->_logger->info("Download duration: ".(microtime() - $start));
    }
}
