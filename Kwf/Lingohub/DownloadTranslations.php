<?php
namespace Kwf\Lingohub;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Kwf_Lingohub_DownloadTranslations extends Command
{
    protected function configure()
    {
        $this->setName('downloadTranslations')
            ->setDescription('Download translations for every package defining lingohub project');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Reading kwf-po file</info>');
    }
}
