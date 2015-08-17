<?php
namespace Kwf\Lingohub;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Kwf\Lingohub\Config\Config;

class DownloadTranslationsScript extends Command
{
    protected function configure()
    {
        $this->setName('downloadTranslations')
            ->setDescription('Download translations for every package defining lingohub project')
            ->addOption('update', 'u', InputOption::VALUE_OPTIONAL, 'Download latest version');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_deleteTrlFiles();
        $download = new DownloadTranslations(new ConsoleLogger($output), new Config());
        $download->setUpdateDownloadedTrlFiles($input->getOption('update'));
        $download->downloadTrlFiles();
    }

    private function _deleteTrlFiles()
    {
        $composerJsonFilePaths = DownloadTranslations::getComposerJsonFiles();
        foreach ($composerJsonFilePaths as $composerJsonFilePath) {
            $trlDir = dirname($composerJsonFilePath).'/trl';
            if (!is_dir($trlDir)) continue;
            array_map('unlink', glob("$trlDir/*.*"));
            rmdir($trlDir);
        }
    }
}
