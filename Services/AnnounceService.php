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
        $mmobjColl = $this->dm->getDocumentCollection('PumukitSchemaBundle:MultimediaObject');
        $filters = $this->dm->getFilterCollection()
            ->getFilterCriteria($this->mmobjRepo->getClassMetadata());

        $pipeline = array(
            array('$match' => $filters),
            array('$sort' => array('public_date' => 1)),
            array('$group' => array('_id' => '$series', 'id' => array('$first' => '$_id'))),
            array('$limit' => $limit),
        );
        $aggregation = $mmobjColl->aggregate($pipeline);

        $ids = array();
        foreach ($aggregation as $element) {
            $ids[] = $element['id'];
        }

        $queryBuilderMms = $this->mmobjRepo->createStandardQueryBuilder()
            ->addAnd(array('_id' => array('$in' => $ids)))
            ->sort(array('public_date' => -1))
            ->limit($limit);
        $last = $queryBuilderMms->getQuery()->execute()->toArray();

        if ($limit != count($last)) {
            return array_merge(
                $last,
                $this->mmobjRepo->findStandardBy(
                    $criteria = array('_id' => array('$nin' => $ids)),
                    array('public_date' => -1),
                    $limit - count($last)
                )
            );
        }

        return $last;
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
