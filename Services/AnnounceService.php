<?php

namespace Pumukit\Geant\WebTVBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;

class AnnounceService
{
    private $mmobjRepo;
    private $dm;

    public function __construct(DocumentManager $documentManager)
    {
        $this->dm = $documentManager;
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
    }

    public function getLast($limit = 3)
    {
        //Get last objects without errors.
        $lastMms = $this->mmobjRepo->findStandardBy(array(), array('public_date' => -1), $limit, 0);
        return $lastMms;
    }

    public function getLatestUploadsByDates($dateStart, $dateEnd)
    {
        $queryBuilderMms = $this->mmobjRepo->createStandardQueryBuilder()
          ->field('public_date')->range($dateStart, $dateEnd)
          ->sort(array('public_date' => -1));

        return $queryBuilderMms->getQuery()->execute()->toArray();
    }

    /**
     * Gets the next latest uploads, starting with the month given and looking 24 months forward.
     * If not, returns an empty array.
     *
     * @return array
     */
    public function getNextLatestUploads($date)
    {
        $counter = 0;
        $dateStart = clone $date;
        $dateStart->modify('first day of next month');
        $dateEnd = clone $date;
        $dateEnd->modify('last day of next month');
        $dateEnd->setTime(23, 59, 59);
        do {
            ++$counter;
            $dateStart->modify('first day of last month');
            $dateEnd->modify('last day of last month');
            $last = $this->getLatestUploadsByDates($dateStart, $dateEnd);
        } while (empty($last) && $counter < 24);

        return array($dateEnd, $last);
    }
}
