<?php
namespace Kwf\Lingohub;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

class DownloadTranslationsScript extends Command
{
    protected function configure()
    {
        $this->setName('downloadTranslations')
            ->setDescription('Download translations for every package defining lingohub project');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $download = new DownloadTranslations(new ConsoleLogger($output));
        $download->downloadTrlFiles();
    }
}
