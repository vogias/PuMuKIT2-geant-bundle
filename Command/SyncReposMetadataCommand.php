<?php

namespace Pumukit\Geant\WebTVBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncReposMetadataCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        ->setName('geant:syncrepos')
        ->setDescription('Imports Geant feed and publishes on PuMuKIT.')
        ->setHelp($this->getCommandHelpText())
        ->addOption(
                'Wall',
                'W',
                InputOption::VALUE_NONE,
                'If set, the task will output the Warnings.'
            )
        ->addOption(
                'show-progress-bar',
                'b',
                InputOption::VALUE_NONE,
                'If set, the task will output a symfony style progress bar.'
            )
        ->addOption(
                'repos-directory',
                'd',
                InputOption::VALUE_OPTIONAL,
                'If set, the task will use the repos-directory (/tmp/pmkgeant by default)'
            );
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = $this->getHelper('formatter');
        $text = $this->getCommandASCIIHeader();
        $formattedBlock = $formatter->formatBlock($text, 'comment', true);
        $output->writeln($formattedBlock);
        //EXECUTE SERVICE
        $feedSyncService = $this->getContainer()->get('pumukit_web_tv.geant.feedsync');
        $optWall = $input->getOption('Wall') ? true : false;
        $show_bar = $input->getOption('show-progress-bar') ? true : false;
        $repos_dir = $input->getOption('repos-directory');
        $feedSyncService->syncRepos($output, $optWall, $show_bar, $repos_dir);
        //SHUTDOWN HAPPILY
    }

    protected function getCommandHelpText()
    {
        return <<<EOT
When executed it searchs for all 'provider' tags and then it finds a 'json' for each. If it cannot find them, it loads the default metadata.
EOT;
    }

    protected function getCommandASCIIHeader()
    {
        return <<<EOT
:::Command to Sync Repos JSONs:::
EOT;
    }
}
