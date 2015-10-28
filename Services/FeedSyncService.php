<?php
namespace Pumukit\Geant\WebTVBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Session\Session;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\TagService;
use Pumukit\SchemaBundle\Services\PersonService;


/**
*  Service that iterates over the FeedSyncClientService responses, it processes them using the FeedProcesserService and then inserts/updates the object into the database.
*
*/
class FeedSyncService
{
    private $factoryService;
    private $tagService;
    private $personService;
    private $feedClientService;
    private $feedProcesserService;
    private $seriesRepo;
    private $mmobjRepo;
    private $tagRepo;
    private $personRepo;
    private $roleRepo;
    private $dm;

    public function __construct(FactoryService $factoryService, TagService $tagService, PersonService $personService, FeedSyncClientService $feedClientService,
    FeedProcesserService $feedProcesserService,  DocumentManager $dm)
    {
        $this->factoryService = $factoryService;
        $this->tagService = $tagService;
        $this->personService = $personService;
        $this->feedClientService = $feedClientService;
        $this->feedProcesserService = $feedProcesserService;
        $this->dm = $dm;
        $this->init();
    }
    public function init()
    {
        $this->seriesRepo = $this->dm->getRepository('PumukitSchemaBundle:Series');
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $this->tagRepo = $this->dm->getRepository('PumukitSchemaBundle:Tag');
        $this->personRepo = $this->dm->getRepository('PumukitSchemaBundle:Person');
        $this->roleRepo = $this->dm->getRepository('PumukitSchemaBundle:Role');
    }

    public function sync($limit = null)
    {
        $terenaGenerator = $this->feedClientService->getFeed( $limit );
        foreach( $terenaGenerator as $terena) {
            var_dump($terena);
            try {
                $parsedTerena = $this->feedProcesserService->process( $terena );
                var_dump( $parsedTerena );
            } catch (\Exception $e) {
                //Log exception error.
                echo "$e";
                continue;
            }
            try {
                $this->syncMmobj($parsedTerena);
            }
            catch (\Exception $e) {
                echo "$e";
                continue;
            }
        }
    }

    public function syncMmobj( $parsedTerena )
    {
        $factory = $this->factoryService;
        $mmobj = $this->mmobjRepo->createQueryBuilder()
                                 ->field('properties.geant_id')
                                 ->equals($parsedTerena['identifier'])
                                 ->getQuery()
                                 ->getSingleResult();
        //We assume the 'provider' property of a feed won't change for the same Geant Feed Resource.
        //If it changes, the mmobj would keep it's original provider.
        if(!isset($mmobj)) {
            $series = $this->seriesRepo->createQueryBuilder()
                                 ->field('properties.geant_provider')
                                 ->equals($parsedTerena['provider'])
                                 ->getQuery()
                                 ->getSingleResult();
            if(!isset($series)) {
                $series = $factory->createSeries();
                $series->setProperty('geant_provider',$parsedTerena['provider']);
                $series->setTitle($parsedTerena['provider']);
            }
            $mmobj = $factory->createMultimediaObject($series);
            //Add 'provider' tag
            $mmobj->setProperty('geant_id', $parsedTerena['identifier']);
        }

        //METADATA
        $this->syncMetadata($mmobj, $parsedTerena);

        //TAGS
        $this->syncTags($mmobj, $parsedTerena);

        //PEOPLE
        $this->syncPeople($mmobj, $parsedTerena);

        //TRACK
        $this->syncTrack($mmobj, $parsedTerena);

        //SAVE CHANGES
        $this->dm->persist($mmobj);
        $this->dm->flush();
    }

    public function syncMetadata(MultimediaObject $mmobj, $parsedTerena)
    {
        $mmobj->setTitle($parsedTerena['title']);
        $mmobj->setDescription($parsedTerena['description']);
        foreach($parsedTerena['keywords'] as $keyword) {
            $mmobj->setKeyword($keyword);
        }
        $mmobj->setLicense($parsedTerena['license']);
        $mmobj->setCopyright($parsedTerena['copyright']);
        $mmobj->setPublicDate($parsedTerena['public_date']);
        $mmobj->setRecordDate($parsedTerena['record_date']);
    }

    public function syncTags(MultimediaObject $mmobj, $parsedTerena)
    {
        foreach($parsedTerena['tags'] as $parsedTag) {
            $tag = $this->tagRepo->findOneByCod($parsedTag);//First we search by code on the database (it should be iTunesU, but could be other)

            if(!isset($tag))  //Second we search by title on the database (again, it should be iTunesU, but could be other)
                $tag = $this->tagRepo->findOneByTitle($parsedTag);

            if(!isset($tag))  //Now we start getting tricky. We search the cod, but adding 'U' (It should be UNESCO)
                $tag = $this->tagRepo->findOneByCod(sprintf('U%s',$parsedTag));

            if(!isset($tag)) { //If we can't find it here, all hope is lost. We log it and continue.
                echo sprintf('Warning: The tag with cod/title %s from the Feed ID:%s does not exist on PuMuKIT',$parsedTag,$parsedTerena['identifier']);
                continue;
            }

            //If the tag turned out to be from UNESCO, we try to add the iTunesU mapped tag
            if($tag->isDescendantOfByCod('UNESCO')) {
                $mappedItunesTags = $this->feedProcesserService->mapCodeToItunes(sprintf('U%s'),substr($parsedTag,0,3));
                foreach($mappedItunesTags as $itunesTags) {
                    $this->tagService->addTagToMultimediaObject($mmobj, $itunesTags->getId(), false);
                }
            }
            $this->tagService->addTagToMultimediaObject($mmobj, $tag->getId(), false);
        }
    }

    public function syncPeople(MultimediaObject $mmobj, $parsedTerena)
    {
        foreach( $parsedTerena['people'] as $contributor) {
            $person = $peopleRepo->findOneByCod($contributor['name']);
            if(!isset($person)) { //If the person doesn't exist, create a new one.
                $person = new Person();
                $person->setName($contributor['name']);
            }

            $role = $roleRepo->findOneByCod($contributor['role']);
            if(!isset($role))  //Workaround for PuMuKIT. The 'Cod' field is not consistent, some are lowercase, some are ucfirst
                $role = $roleRepo->findOneByCod(ucfirst($contributor['role']));

            if(!isset($role)) { //If the role doesn't exist, use 'Participant'.
                $role = $roleRepo->findOneByCod('Participant'); // <-- This cod is ucfirst, but others are lowercase.
            }

            $this->personService->createRelationPerson($person, $role, $mmobj);
        }
    }

    public function syncTrack(MultimediaObject $mmobj, $parsedTerena)
    {

    }
}
