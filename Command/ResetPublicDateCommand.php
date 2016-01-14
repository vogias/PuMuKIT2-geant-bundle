<?php

namespace Pumukit\Geant\WebTVBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetPublicDateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
        ->setName('geant:reset_public_date')
        ->setDescription('Resets the public date of ALL mmobjs to TODAY');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->formatter = $this->getHelper('formatter');
        $text = "Initializing all dates to 'public'...";
        $this->writeOutput($output, $text, 'comment');

        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');

        $numberMmobjs = $this->mmobjRepo->createQueryBuilder()->count()->getQuery()->execute();
        $allMmobjs = $this->mmobjRepo->createQueryBuilder()->getQuery()->execute();

        $todayDate = new \MongoDate();

        $text = sprintf('There are %s objects. Initializing to %s', $numberMmobjs, $todayDate->toDateTime()->format('d/m/Y'));
        $this->writeOutput($output, $text, 'comment');

        $count = 0;
        $time_started = microtime(true);
        foreach ($allMmobjs as $mmobj) {
            ++$count;
            $mmobj->setPublicDate($todayDate);
            $this->dm->persist($mmobj);
            if ($count % 300 == 0) {
                $this->dm->flush();
                $this->dm->clear();
                $this->showProgressEstimation($output, $count, $numberMmobjs, $time_started);
            }
        }
        $this->showProgressEstimation($output, $count, $numberMmobjs, $time_started);
        $text = 'Script FINISHED.';
        $this->writeOutput($output, $text, 'comment');
    }

    protected function writeOutput($output, $text, $type)
    {
        $formattedBlock = $this->formatter->formatBlock($text, $type, true);
        $output->writeln($formattedBlock);
    }

    protected function showProgressEstimation($output, $processed, $total, $time_started)
    {
        $now = microtime(true);
        $origin = $time_started;
        $elapsed_sec = (float) ($now - $origin);
        $eta_sec = ($total * $elapsed_sec) / $processed;
        $eta_min = $eta_sec / 60;
        $elapsed_min = $elapsed_sec / 60;
        $processed_min = (integer) ($processed / $elapsed_min);
        $text = sprintf("Progress %s/%s\n", $processed, $total);
        $text .= 'Elapsed time: '.sprintf('%.2F', $elapsed_min).
                ' minutes - estimated: '.sprintf('%.2F', $eta_min).
                ' minutes. Speed: '.$processed_min." mmobjs / minute.\n";
        $this->writeOutput($output, $text, 'comment');
    }
}
