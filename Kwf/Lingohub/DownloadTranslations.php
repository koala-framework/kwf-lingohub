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
        $downloadErrors = array();
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

                try {
                    $export = $this->_triggerAndWaitForExport($accountName, $projectName, $params);
                } catch(LingohubException $e) {
                    echo "LingohubException: ".$e->getMessage()."\n";
                    $downloadErrors[] = $projectName;
                    continue;
                }
                foreach ($export['resourceExports'] as $resource) {
                    $poFilePath = $trlTempDir.'/'.$resource['filePath'];
                    if ($resource['status'] !== 'SUCCESS') {
                        $this->_logger->alert("Export for {$resource['filePath']} failed...");
                    } else {
                        $this->_logger->notice("Downloading {$resource['filePath']}");
                        try {
                            $file = $this->_getData($resource['downloadUrl']);
                        } catch(LingohubException $e) {
                            echo "LingohubException: ".$e->getMessage()."\n";
                            $downloadErrors[] = $projectName;
                            break;
                        }

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
        if (!empty($downloadErrors)) {
            echo "\nDownload errors in the following projects: \n";
            foreach($downloadErrors as $projectName) {
                echo $projectName . "\n";
            }
        }
    }

    private function _triggerAndWaitForExport($accountName, $projectName, $params)
    {
        $triggerCreateExportUrl = "https://api.lingohub.com/v1/$accountName"
            ."/projects/$projectName/exports"
            ."?".http_build_query($params);
        $export = json_decode($this->_postData($triggerCreateExportUrl), true);

        while(in_array($export['status'], array('PROCESSING', 'SCHEDULED', 'NEW'))) {
            $getExportUrl = "https://api.lingohub.com/v1/$accountName"
                ."/projects/$projectName/exports/{$export['id']}"
                ."?".http_build_query($params);
            sleep(1);
            $export = json_decode($this->_getData($getExportUrl), true);
        }
        if ($export['status'] !== 'SUCCESS') {

            if ($export['status'] === 'ERROR') {
                $this->_logger->critical("Post to start export failed: " . $export['errorDetails']);
                throw new LingohubException("Post to start lingohub export failed!");
            } else {
                throw new LingohubException("Unexpected status from lingohub export-api");
            }
        }
        return $export;
    }

    private function _postData($url)
    {
        $this->_logger->debug("posting (POST $url)");
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_CONNECTTIMEOUT => 5
        ));

        $count = 0;
        $response = false;
        while ($response === false && $count < 5) {
            if ($count != 0) {
                sleep(5);
                $this->_logger->warning("retry posting... (POST {$url})");
            }
            $response = curl_exec($ch);
            $count++;
        }
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            throw new LingohubException('Request to '.$url.' failed with '.curl_getinfo($ch, CURLINFO_HTTP_CODE).': '.$response);
        }
        return $response;
    }

    private function _getData($url)
    {
        $this->_logger->debug("fetching $url");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $count = 0;
        $response = false;
        while ($response === false && $count < 5) {
            if ($count != 0) {
                sleep(5);
                $this->_logger->warning("Try again downloading file... {$url}");
            }
            $response = curl_exec($ch);
            $count++;
        }
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            throw new LingohubException('Request to '.$url.' failed with '.curl_getinfo($ch, CURLINFO_HTTP_CODE).': '.$response);
        }
        return $response;
    }
}
