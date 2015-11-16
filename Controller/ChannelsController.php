<?php

namespace Pumukit\Geant\WebTVBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;
use Pagerfanta\Pagerfanta;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Tag;

class ChannelsController extends Controller
{
    private $categories = array(
      1 => array('title' => 'Health and Medicine', 'map' => array('103')),
      2 => array('title' => 'Humanities', 'map' => array('100','102','104','105','106','107', '112')),
      3 => array('title' => 'Science', 'map' => array('108','109')),
      4 => array('title' => 'Technology', 'map' => array('101')),
      5 => array('title' => 'Legal and Social', 'map' => array('110', '111'))
    );
    
    /**
    * @Route("/category/{category}", defaults={"category" = null})
    * @Template("PumukitWebTVBundle:Search:index.html.twig")
    */
    public function multimediaObjectsAction($category, $useBlockedTagAsGeneral = false, Request $request)
    {
        if (!array_key_exists($category, $this->categories)) {
            throw $this->createNotFoundException(sprintf('Category \'%s\' does not exist', $category));
        }

        $title = $this->categories[$category]['title'];
        $this->get('pumukit_web_tv.breadcrumbs')->addList($title, 'pumukit_geant_webtv_channels_multimediaobjects', array('category' => $category));

        
        // --- Get Tag Parent for Tag Fields ---
        $parentTag = $this->getParentTag();
        // --- END Get Tag Parent for Tag Fields ---

        // --- Get Variables ---
        $searchFound = $request->query->get('search');
        $tagsFound = $request->query->get('tags');
        $typeFound = $request->query->get('type');
        $durationFound = $request->query->get('duration');
        $startFound = $request->query->get('start');
        $endFound = $request->query->get('end');
        $yearFound = $request->query->get('year');
        $languageFound = $request->query->get('language');
        // --- END Get Variables --
        // --- Create QueryBuilder ---
        $mmobjRepo = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');
        $queryBuilder = $mmobjRepo->createStandardQueryBuilder();

        $queryBuilder->field('tags.cod')->in($this->categories[$category]['map']);
        
        $queryBuilder = $this->searchQueryBuilder($queryBuilder, $searchFound);
        $queryBuilder = $this->typeQueryBuilder($queryBuilder, $typeFound);
        $queryBuilder = $this->durationQueryBuilder($queryBuilder, $durationFound);
        $queryBuilder = $this->dateQueryBuilder($queryBuilder, $startFound, $endFound, $yearFound);
        $queryBuilder = $this->languageQueryBuilder($queryBuilder, $languageFound);
        $queryBuilder = $this->tagsQueryBuilder($queryBuilder, $tagsFound);
        // --- END Create QueryBuilder ---
        // --- Execute QueryBuilder and get paged results ---
        $pagerfanta = $this->createPager($queryBuilder, $request->query->get('page', 1));
        // --- Query to get existing languages ---
        $searchLanguages = $this->get('doctrine_mongodb')
            ->getRepository('PumukitSchemaBundle:MultimediaObject')
            ->createStandardQueryBuilder()->distinct('tracks.language')
            ->getQuery()->execute();
        // -- Init Number Cols for showing results ---
        $numberCols = 2;
        if ($this->container->hasParameter('columns_objs_search')) {
            $numberCols = $this->container->getParameter('columns_objs_search');
        }


        $tag = new Tag();
        $tag->setCod($this->categories[$category]['map'][0]);
        $tag->setTitle($title);
        /* Added search years for Geant search.*/
        // --- Query to get oldest date ---
        $firstMmobj = $this->get('doctrine_mongodb')
        ->getRepository('PumukitSchemaBundle:MultimediaObject')
        ->createStandardQueryBuilder()->sort('record_date','asc')->limit(1)
        ->getQuery()->getSingleResult();
        $minRecordDate = $firstMmobj->getRecordDate()->format('m/d/Y');
        $maxRecordDate = date('m/d/Y');
        // --- Query to get years for the 'Year' select form. ---
        $searchYears = array();
        $maxYear = date('Y');
        $tempYear = $firstMmobj->getRecordDate()->format('Y');
        while($tempYear <= $maxYear) {
            $searchYears[] = $tempYear;
            $tempYear++;
        }

        // --- RETURN ---
        return array('type' => 'multimediaObject',
            'objects' => $pagerfanta,
            'parent_tag' => $parentTag,
            'parent_tag_optional' => null,
            'tags_found' => $tagsFound,
            'number_cols' => $numberCols,
            'languages' => $searchLanguages,
            'blocked_tag' => $tag,
            'search_years' => $searchYears,
        );
    }

    private function createPager($objects, $page)
    {
        $limit = 10;
        if ($this->container->hasParameter('limit_objs_search')) {
            $limit = $this->container->getParameter('limit_objs_search');
        }
        $adapter = new DoctrineODMMongoDBAdapter($objects);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setCurrentPage($page);

        return $pagerfanta;
    }


    private function getParentTag()
    {
        $tagRepo = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:Tag');
        $searchByTagCod = 'PROVIDER';
        $parentTag = $tagRepo->findOneByCod($searchByTagCod);
        if (!isset($parentTag)) {
            throw new \Exception(sprintf('The parent Tag with COD:  \' %s  \' does not exist. Check if your tags are initialized and that you added the correct \'cod\' to parameters.yml (search.parent_tag.cod)', $searchByTagCod));
        }
        return $parentTag;
    }

    // ========= queryBuilder functions ==========

    private function searchQueryBuilder($queryBuilder, $searchFound)
    {
        if ($searchFound != '') {
            $queryBuilder->field('$text')->equals(array('$search' => $searchFound));
        }

        return $queryBuilder;
    }

    private function typeQueryBuilder($queryBuilder, $typeFound)
    {
        if ($typeFound) {
            $queryBuilder->field('tracks.only_audio')->equals($typeFound == 'Audio');
        }

        return $queryBuilder;
    }

    private function durationQueryBuilder($queryBuilder, $durationFound)
    {
        if ($durationFound != '') {
            if ($durationFound == '-5') {
                $queryBuilder->field('tracks.duration')->lte(300);
            }
            if ($durationFound == '-10') {
                $queryBuilder->field('tracks.duration')->lte(600);
            }
            if ($durationFound == '-30') {
                $queryBuilder->field('tracks.duration')->lte(1800);
            }
            if ($durationFound == '-60') {
                $queryBuilder->field('tracks.duration')->lte(3600);
            }
            if ($durationFound == '+60') {
                $queryBuilder->field('tracks.duration')->gt(3600);
            }
        }

        return $queryBuilder;
    }

    private function dateQueryBuilder($queryBuilder, $startFound, $endFound, $yearFound=null)
    {
        if ($yearFound) {
            $start = \DateTime::createFromFormat('d/m/Y:H:i:s', sprintf('01/01/%s:00:00:01',$yearFound));
            $end = \DateTime::createFromFormat('d/m/Y:H:i:s', sprintf('01/01/%s:00:00:01',($yearFound)+1));
            $queryBuilder->field('record_date')->gte($start);
            $queryBuilder->field('record_date')->lt($end);
        }
        else {
            if ($startFound) {
                $start = \DateTime::createFromFormat('d/m/Y', $startFound);
                $queryBuilder->field('record_date')->gt($start);
            }
            if ($endFound) {
                $end = \DateTime::createFromFormat('d/m/Y', $endFound);
                $queryBuilder->field('record_date')->lt($end);
            }
        }

        return $queryBuilder;
    }

    private function languageQueryBuilder($queryBuilder, $languageFound)
    {
        if ($languageFound) {
            $queryBuilder->field('tracks.language')->equals($languageFound);
        }

        return $queryBuilder;
    }

    private function tagsQueryBuilder($queryBuilder, $tagsFound)
    {
        if ($tagsFound !== null) {
            $tagsFound = array_values(array_diff($tagsFound, array('All', '')));
        }

        if (count($tagsFound) > 0) {
            $queryBuilder->field('tags.cod')->all($tagsFound);
        }

        return $queryBuilder;
    }
    // ========== END queryBuilder functions =========
}
