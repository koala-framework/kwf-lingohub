<?php
namespace Kwf\Lingohub;
use Psr\Log\LoggerInterface;
use Kwf\Lingohub\Config\ConfigInterface;

class DownloadTranslations
{
    static $TEMP_TRL_FOLDER = 'koala-framework-lingohub-trl';
    static $TEMP_LAST_UPDATE_FILE = 'last_update.txt';

    protected $_logger;
    protected $_config;
    protected $_updateDownloadedTrlFiles = false;

    public function __construct(LoggerInterface $logger, ConfigInterface $config)
    {
        $this->_logger = $logger;
        $this->_config = $config;
    }

    public function setForceDownloadTrlFiles($download)
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
            $lastDownloadTimestamp = strtotime(substr(file_get_contents($this->_getLastUpdateFile($account, $project)), 0, strlen('HHHH-MM-DD')));
            $downloadFiles = strtotime('today') > $lastDownloadTimestamp;
        }
        return $downloadFiles;
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
            $accountName = strtolower($kwfLingohub->account);
            $projectName = strtolower($kwfLingohub->project);
            $trlTempDir = $this->_getTempFolder($accountName, $projectName);
            if ($this->_checkDownloadTrlFiles($accountName, $projectName)) {
                if (!file_exists($trlTempDir)) {
                    mkdir($trlTempDir, 0777, true);//write and read for everyone
                }
                $this->_logger->warning("Checking/Downloading resources of {$kwfLingohub->account}/{$kwfLingohub->project}");
                $params = array( 'auth_token' => $this->_config->getApiToken() );
                $resourcesUrl = "https://api.lingohub.com/v1/$accountName"
                    ."/projects/$projectName/resources"
                    ."?".http_build_query($params);
                $content = $this->_downloadFile($resourcesUrl);
                if ($content === false) {
                    throw new LingohubException('Service unavailable');
                }
                $resources = json_decode($content);
                if ($resources == null) {
                    throw new LingohubException('No json returned');
                }
                if (!isset($resources->members)) {
                    $this->_logger->debug($content);
                    throw new LingohubException('Invalid response, resource doesn\'t contain members');
                }
                foreach ($resources->members as $resource) {
                    $poFilePath = $trlTempDir.'/'.$resource->project_locale.'.po';
                    $this->_logger->notice("Downloading {$resource->name}");
                    $urlParts = parse_url($resource->links[0]->href);
                    $separator =  isset($urlParts['query']) ? '&' : '?';
                    $url = $resource->links[0]->href.$separator.http_build_query($params);
                    $this->_logger->info('Calling Url: '.$url);
                    $file = $this->_downloadFile($url);
                    if ($file === false) {
                        throw new LingohubException('Url provided from Lingohub not working: '.$url);
                    }
                    if (strpos($file, '"Content-Type: text/plain; charset=UTF-8"') === false) {
                        $poHeader = "msgid \"\"\n"
                                   ."msgstr \"\"\n"
                                   ."\"Content-Type: text/plain; charset=UTF-8\"\n\n";
                        $file = $poHeader.$file;
                    }
                    file_put_contents($poFilePath, $file);
                }
                file_put_contents($this->_getLastUpdateFile($accountName, $projectName), date('Y-m-d H:i:s'));
            }
            if (!file_exists(dirname($composerJsonFilePath).'/trl/')) {
                mkdir(dirname($composerJsonFilePath).'/trl/', 0777, true);//write and read for everyone
            }
            foreach (scandir($trlTempDir) as $file) {
                if (substr($file, 0, 1) === '.') continue;
                copy($trlTempDir.'/'.$file, dirname($composerJsonFilePath).'/trl/'.basename($file));
            }
        }
    }

    private function _downloadFile($url)
    {
        $this->_logger->debug("fetching $url");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $count = 0;
        $file = false;
        while ($file === false && $count < 5) {
            if ($count != 0) {
                sleep(5);
                $this->_logger->warning("Try again downloading file... {$url}");
            }
            $file = curl_exec($ch);
            $count++;
        }
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            throw new LingohubException('Request to '.$url.' failed with '.curl_getinfo($ch, CURLINFO_HTTP_CODE).': '.$file);
        }
        return $file;
    }
}
