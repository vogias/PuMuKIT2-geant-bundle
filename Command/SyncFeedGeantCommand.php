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
        ->setHelp( $this->getCommandHelpText() );
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = $this->getHelper('formatter');
        $text = $this->getCommandASCIIHeader();
        $formattedBlock = $formatter->formatBlock($text, 'comment', true);
        $output->writeln($formattedBlock);
        //EXECUTE SERVICE
        $feedSyncService = $this->getContainer()->get('pumukit_web_tv.geant.feedsync');
        $feedSyncService->sync(1);
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
                _____
               /  ___|
               \ `--. _   _ _ __   ___
                `--. | | | | '_ \ / __|
               /\__/ | |_| | | | | (__
               \____/ \__, |_| |_|\___|
                       __/ |
                      |___/

EOT;
    }
}
