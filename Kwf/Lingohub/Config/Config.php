<?php
namespace Kwf\Lingohub\Config;

class Config implements ConfigInterface
{
    protected $_apiToken;
    public function __construct()
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
        $this->_apiToken = $config->apiToken;
    }

    /**
    * Adopted from https://github.com/composer/composer/blob/9f9cff558e5f447165f4265f320b2b1178f18301/src/Composer/Factory.php
    */
    private function _getHomeDir()
    {
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            if (!getenv('APPDATA')) {
                throw new Exception("The APPDATA environment variable must be set for kwf-lingohub to run correctly");
            }
            $home = strtr(getenv('APPDATA'), '\\', '/') . '/Config';
        } else {
            if (!getenv('HOME')) {
                throw new Exception("The HOME environment variable must be set for kwf-lingohub to run correctly");
            }
            $home = rtrim(getenv('HOME'), '/') . '/.config';
        }
        return $home;
    }

    public function getApiToken()
    {
        return $this->_apiToken;
    }
}