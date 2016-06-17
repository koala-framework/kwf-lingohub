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
            ->setDescription('Download translations for every package defining lingohub project');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $download = new DownloadTranslations(new ConsoleLogger($output), new Config());
        $download->setForceDownloadTrlFiles(true);
        try {
            $download->downloadTrlFiles();
        } catch(LingohubException $e) {
            echo "LingohubException: ".$e->getMessage()."\n";
            return 1;
        }
    }
}
