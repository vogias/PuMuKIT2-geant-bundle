<?php

namespace Pumukit\Geant\WebTVBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncFeedGeantCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        ->setName('geant:syncfeed:import')
        ->setDescription('Imports Geant feed and publishes on PuMuKIT.')
        ->setHelp( $this->getCommandHelpText() )
        ->addOption(
                'Wall',
                'W',
                InputOption::VALUE_NONE,
                'If set, the task will output the Warnings.'
            )
        ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'If set, the task will only import "limit" number of elements.',
                0
            )
        ->addOption(
                'provider',
                'P',
                InputOption::VALUE_REQUIRED,
                'If set, the task will import elements from a particular provider only.'
            )
        ->addOption(
                'showids',
                'S',
                InputOption::VALUE_NONE,
                'If set, the task will show more information than usual.'
            )
        ->addOption(
                'show-progress-bar',
                'b',
                InputOption::VALUE_NONE,
                'If set, the task will output a symfony style progress bar.'
            )    ;
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = $this->getHelper('formatter');
        $text = $this->getCommandASCIIHeader();
        $formattedBlock = $formatter->formatBlock($text, 'comment', true);
        $output->writeln($formattedBlock);
        //EXECUTE SERVICE
        $feedSyncService = $this->getContainer()->get('pumukit_web_tv.geant.feedsync');
        $optWall = $input->getOption('Wall')?true:false;
        $limit = $input->getOption('limit')?:0;
        $provider = $input->getOption('provider');
        $verbose = $input->getOption('showids')?true:false;
        $show_bar = $input->getOption('show-progress-bar')?true:false;
        $feedSyncService->sync($output, $limit, $optWall, $provider, $verbose, $show_bar);
        //SHUTDOWN HAPPILY
    }






    protected function getCommandHelpText()
    {
        return <<<EOT
Command to sync the Geant feed data into the database and published it on the WebTV.

The --force parameter has to be used to actually drop the database.

EOT;
    }



    protected function getCommandASCIIHeader()
    {
        return <<<EOT

                        _
                       | |
  __ _  ___  __ _ _ __ | |_   ___ _   _ _ __   ___
 / _` |/ _ \/ _` | '_ \| __| / __| | | | '_ \ / __|
| (_| |  __/ (_| | | | | |_  \__ \ |_| | | | | (__
 \__, |\___|\__,_|_| |_|\__| |___/\__, |_| |_|\___|
    / |                            __/ |
 |___/                            |___/

:::Command to Sync the PuMuKIT Database with the Geant Project Feed:::
EOT;
    }
}
