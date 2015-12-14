<?php
namespace Pumukit\Geant\WebTVBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Session\Session;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Person;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\Track;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\TagService;
use Pumukit\SchemaBundle\Services\PersonService;
use Pumukit\SchemaBundle\Services\MultimediaObjectPicService;
use Symfony\Component\Console\Helper\ProgressBar;

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

    private $providerRootTag;
    private $webTVTag;
    private $optWall; //If true, prints warnings.

    private $VIDEO_EXTENSIONS = array('mp4', 'm4v', 'm4b');
    private $AUDIO_EXTENSIONS = array('mp3', 'm4a', 'wav', 'ogg');



    public function __construct(FactoryService $factoryService, TagService $tagService, PersonService $personService, MultimediaObjectPicService $mmsPicService, FeedSyncClientService $feedClientService,
                                FeedProcesserService $feedProcesserService,  DocumentManager $dm, $dataFolder)
    {
        //Schema Services
        $this->factoryService = $factoryService;
        $this->tagService = $tagService;
        $this->personService = $personService;
        $this->mmsPicService = $mmsPicService;
        //Geant Sync Services
        $this->feedClientService = $feedClientService;
        $this->feedProcesser = $feedProcesserService;
        $this->dm = $dm;
        $this->dataFolder = $dataFolder;
        $this->init();
    }
    public function init()
    {
        $this->seriesRepo = $this->dm->getRepository('PumukitSchemaBundle:Series');
        $this->mmobjRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $this->tagRepo = $this->dm->getRepository('PumukitSchemaBundle:Tag');
        $this->personRepo = $this->dm->getRepository('PumukitSchemaBundle:Person');
        $this->roleRepo = $this->dm->getRepository('PumukitSchemaBundle:Role');
        $this->providerRootTag = $this->tagRepo->findOneByCod('PROVIDER');
        if(!isset($this->providerRootTag)) {
            $newTag = new Tag();
            $newTag->setParent($this->tagRepo->findOneByCod('ROOT'));
            $newTag->setCod('PROVIDER');
            $newTag->setMetatag(true);
            $newTag->setDisplay(false);
            $newTag->setTitle('Provider');
            $this->dm->persist($newTag);
            $this->providerRootTag = $newTag;
        }
        $this->webTVTag = $this->tagRepo->findOneByCod('PUCHWEBTV');
        if(!isset($this->webTVTag)) {
            throw new FeedSyncException('Tag: PUCHWEBTV does not exists. Did you initialize the repository? (pumukit:init:repo)');
        }
        $this->optWall=false;
    }

    public function sync($output, $limit = 0, $optWall = false, $provider = null, $verbose = false, $setProgressBar = false)
    {
        $this->optWall = $optWall;
        $this->verbose = $verbose;
        $time_started = microtime(true);
        $count = 0;
        $total = $this->feedClientService->getFeedTotal($provider);
        if($limit == 0 || $limit > $total){
            $limit = $total;
        }
        $progressBar = null;
        if($setProgressBar){
            $progressBar = new ProgressBar($output, $total);
            $progressBar->setFormat("<comment>%message%</comment>\n%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%");
            $progressBar->setMessage(' Syncing with Geant Feed...');
            $progressBar->start();
        }
        $terenaGenerator = $this->feedClientService->getFeed( $limit, $provider );
        $lastSyncDate = new \MongoDate();
        foreach( $terenaGenerator as $terena) {
            $count++;
            if($verbose) {
                echo sprintf("\nImporting object: id: %s\n",$terena['identifier']);
            }
            if($count % 10 == 0) {
                $this->showProgressEstimateDuration ($time_started, $count, $total, $progressBar);
            }
            if($count > $limit) {
                break;
            }
            try {
                $parsedTerena = $this->feedProcesser->process( $terena );
            } catch (FeedSyncException $e) {
                if(isset($progressBar)) {
                    //Log exception error.
                    $progressBar->clear();
                    echo sprintf("\nPARSING GENERATOR EXCEPTION: -Message: %s \n",$e->getMessage());
                    echo "\n";
                    $progressBar->display();
                }
                else {
                    echo sprintf("\nPARSING GENERATOR EXCEPTION: -Message: %s \n",$e->getMessage());
                }
                continue;
            }
            try {
                $this->syncMmobj($parsedTerena, $lastSyncDate);
            }
            catch (FeedSyncException $e) {
                if(isset($progressBar)) {
                    $progressBar->clear();
                    echo sprintf("\nSYNC GENERATOR EXCEPTION: \n-Message: %s \n----",$e->getMessage());
                    echo "\n";
                    $progressBar->display();
                }
                else {
                    echo sprintf("\nSYNC GENERATOR EXCEPTION: \n-Message: %s \n----",$e->getMessage());
                }
                continue;
            }
            if($count % 50 == 0) {
                $this->dm->flush();
                $this->dm->clear();
            }
        }
        $this->dm->flush();
        $this->dm->clear();
        if(isset($progressBar)) {
            $progressBar->finish();
        }

    }

    public function syncMmobj( $parsedTerena , \MongoDate $lastSyncDate = null)
    {
        if(!isset($lastSyncDate)) {
            $lastSyncDate = new \MongoDate();
        }
        $factory = $this->factoryService;
        $mmobj = $this->mmobjRepo->createQueryBuilder()
                      ->field('properties.geant_id')
                      ->equals($parsedTerena['identifier'])
                      ->getQuery()
                      ->getSingleResult();

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

        //We assume the 'provider' property of a feed won't change for the same Geant Feed Resource.
        //If it changes, the mmobj would keep it's original provider.
        if(!isset($mmobj)) {
            $mmobj = $factory->createMultimediaObject($series, false);
            $mmobj->setProperty('geant_id', $parsedTerena['identifier']);

            //Add 'provider' tag
            $providerTag = $this->tagRepo->findOneByCod($parsedTerena['provider']);
            if(!isset($providerTag)) {
                $providerTag = new Tag();
                $providerTag->setParent($this->providerRootTag);
                $providerTag->setCod($parsedTerena['provider']);
                $providerTag->setTitle($parsedTerena['provider']);
                $providerTag->setDisplay(true);
                $providerTag->setMetatag(false);
                $this->dm->persist($providerTag);
            }
            $this->tagService->addTagToMultimediaObject($mmobj, $providerTag->getId(), false);
        }
        $mmobj->setProperty('last_sync_date', $lastSyncDate);
        $series->setProperty('last_sync_date', $lastSyncDate);
        //PUBLISH
        $mmobj->setStatus(MultimediaObject::STATUS_PUBLISHED);
        $this->tagService->addTagToMultimediaObject($mmobj, $this->webTVTag->getId(), false);

        //METADATA
        $this->syncMetadata($mmobj, $parsedTerena);

        //TAGS
        $this->syncTags($mmobj, $parsedTerena);

        //PEOPLE
        $this->syncPeople($mmobj, $parsedTerena);

        //TRACK
        $this->syncTrack($mmobj, $parsedTerena);

        //THUMBNAIL
        $this->syncThumbnail($mmobj, $parsedTerena);

        //SAVE CHANGES
        $this->dm->persist($mmobj);
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
        $mmobj->setDuration($parsedTerena['duration']);
    }

    public function syncTags(MultimediaObject $mmobj, $parsedTerena)
    {
        foreach($parsedTerena['tags'] as $parsedTag) {
            $parsedTag = strval($parsedTag); //Sometimes they are ints.
            $tag = $this->tagRepo->findOneByCod($parsedTag);//First we search by code on the database (it should be iTunesU, but could be other)

            if(!isset($tag))  //Second we search by title on the database (again, it should be iTunesU, but could be other)
                $tag = $this->tagRepo->findOneByTitle($parsedTag);

            if(!isset($tag))  //Now we start getting tricky. We search the cod, but adding 'U' (It should be UNESCO)
                $tag = $this->tagRepo->findOneByCod(sprintf('U%s',$parsedTag));

            if(!isset($tag)) { //If we can't find it here, all hope is lost. We log it and continue.
                if($this->optWall){
                    echo "\n".sprintf('Warning: The tag with cod/title %s from the Feed ID:%s does not exist on PuMuKIT',$parsedTag,$parsedTerena['identifier']);
                }
                continue;
            }

            //If the tag turned out to be from UNESCO, we try to add the iTunesU mapped tag
            if($tag->isDescendantOfByCod('UNESCO')) {
                $mappedItunesTags = $this->feedProcesser->mapCodeToItunes(sprintf('U%s',substr($parsedTag,0,3)));
                foreach($mappedItunesTags as $itunesTag) {
                    $iTag = $this->tagRepo->findOneByCod($itunesTag);
                    if(!isset($iTag)) {
                        throw new FeedSyncException(sprintf('Error! The parsed iTunes tag with code: %s  doesnt exists on PuMuKIT. Did you initialize the iTunes repo?' ,$itunesTag));
                    }
                    $this->tagService->addTagToMultimediaObject($mmobj, $iTag->getId(), false);
                }
            }
            $this->tagService->addTagToMultimediaObject($mmobj, $tag->getId(), false);
        }
    }

    public function syncPeople(MultimediaObject $mmobj, $parsedTerena)
    {
        foreach( $parsedTerena['people'] as $contributor) {
            $person = $this->personRepo->findOneByName($contributor['name']);
            if(!isset($person)) { //If the person doesn't exist, create a new one.
                $person = new Person();
                $person->setName($contributor['name']);
                $this->personService->savePerson($person);
            }

            $role = $this->roleRepo->findOneByCod($contributor['role']);
            if(!isset($role))  //Workaround for PuMuKIT. The 'Cod' field is not consistent, some are lowercase, some are ucfirst
                $role = $this->roleRepo->findOneByCod(ucfirst($contributor['role']));

            if(!isset($role)) { //If the role doesn't exist, use 'Participant'.
                $role = $this->roleRepo->findOneByCod('Participant'); // <-- This cod is ucfirst, but others are lowercase.
            }

            $this->personService->createRelationPerson($person, $role, $mmobj, false);
        }
    }

    public function syncTrack(MultimediaObject $mmobj, $parsedTerena)
    {
        $url = $parsedTerena['track_url'];
        $urlExtension = pathinfo((parse_url($parsedTerena['track_url'])['path']), PATHINFO_EXTENSION);
        $track = $mmobj->getTrackWithTag('geant_track');
        if(!isset($track)) {
            $track = new Track();
            $mmobj->addTrack($track);
        }
        $track->addTag('geant_track');
        $track->setLanguage($parsedTerena['language']);
        $track->setDuration($parsedTerena['duration']);
        $track->setVcodec($parsedTerena['track_format']);
        $track->setPath($url);
        $track->setUrl($url);

        $format = explode('/', $parsedTerena['track_format']);
        $formatType = isset($format[0])?$format[0]:null;
        $formatExtension = isset($format[1])?$format[1]:null;

        if( ($formatType == 'video' && in_array($formatExtension, $this->VIDEO_EXTENSIONS)) || in_array($urlExtension, $this->VIDEO_EXTENSIONS)) {
            $track->addTag('display');
            $track->setOnlyAudio(false);
        }
        else if( ($formatType == 'audio' && in_array($formatExtension, $this->AUDIO_EXTENSIONS)) || in_array($urlExtension, $this->AUDIO_EXTENSIONS)) {
            $track->addTag('display');
            $track->setOnlyAudio(true);
        }
        else {
            //We try to create an embed Url. If we can't, it returns false and we'll redirect instead. (When other repositories provides more embedded urls we will change this)
            $embedUrl = $this->feedProcesser->getYoutubeEmbedUrl($url);

            if($embedUrl) {
                $mmobj->setProperty('opencast', true); //Workaround to prevent editing the Schema Filter for now.
                $mmobj->setProperty('iframeable', true);
                $mmobj->setProperty('iframe_url', $embedUrl);
            }
            else {
                $mmobj->setProperty('opencast', true); //Workaround to prevent editing the Schema Filter for now.
                $mmobj->setProperty('redirect', true);
                $mmobj->setProperty('redirect_url', $url);
            }
        }
        $this->dm->persist($track);
        $track->addTag('geant_track');
    }

    public function syncThumbnail(MultimediaObject $mmobj, $parsedTerena)
    {
        $url = $parsedTerena['thumbnail'];
        $pics = $mmobj->getPics();
        if (0 === count($pics)) {
            $mmobj = $this->mmsPicService->addPicUrl($mmobj, $url, false);
        } else {
            foreach($pics as $pic) break; //Woraround to get the first element.
            $pic->setUrl($url);
            $this->dm->persist($pic);
        }
    }

    /**
     * Prints on screen an estimated duration of the script and statistics about its execution.
     *
     */
    //TODO USE Symfony Progress Bar: http://symfony.com/doc/current/components/console/helpers/progressbar.html
    protected function showProgressEstimateDuration($time_started, $processed, $total, $progressBar = null)
    {
        $now         = microtime(true);
        $origin      = $time_started;
        $elapsed_sec = (float) ($now - $origin);
        $eta_sec     = ($total * $elapsed_sec) / $processed;
        $eta_min     = $eta_sec / 60;
        $elapsed_min = $elapsed_sec / 60;
        $processed_min = (integer) ($processed / $elapsed_min);
        if(isset($progressBar)){
            $progressBar->setProgress($processed);
        }
        else {
            echo "\nTerena entry " . $processed . " / " . $total . "\n";
            echo "Elapsed time: " . sprintf('%.2F', $elapsed_min) .
                 " minutes - estimated: " . sprintf('%.2F', $eta_min) .
                 " minutes. Speed: " . $processed_min . " terenas / minute.\n";
        }
    }

    /**
     *
     */
    public function syncRepos($output, $optWall, $show_bar)
    {

        $dataFolder = $this->dataFolder->locateResource('@PumukitGeantWebTVBundle/Resources/data/repos_data');
        $providers = $this->tagRepo->findOneBy(array('cod'=>'PROVIDER'))->getChildren();
        $defaultThumbnail = 'bundles/pumukitgeantwebtv/images/repositories/default_picture.png';
        
        //Progress bar init.
        $total = count($providers);
        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat("<comment>%message%</comment>\n%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%");
        $progressBar->setMessage(' Loading repos metadata');
        $progressBar->start();
                
        foreach($providers as $provider) {
            $fileDir = $dataFolder."/".$provider->getCod().".json";
            if(file_exists($fileDir)) {
                $str = file_get_contents($fileDir);
                $providerData = json_decode($str, true);
                $provider->setProperty('description', $providerData['description']);
                $provider->setTitle($providerData['title']);
                $thumbnailUrl = $this->parseThumbnailUrl($providerData['thumbnail_url']);
                $provider->setProperty('thumbnail_url', $thumbnailUrl);
            }
            else {
                $provider->setProperty('description','');
                $provider->setProperty('thumbnail_url', $defaultThumbnail);
                $provider->setTitle($provider->getCod());
            }
            $this->dm->persist($provider);
            $progressBar->advance();
        }
        $progressBar->finish();
        echo "\nALL LOADED\n";
        $this->dm->flush();
    }
    protected function parseThumbnailUrl($thumbUrl)
    {
        if(strpos($thumbUrl, 'http') !== false)
            return $thumbUrl;
        else
            return '/bundles/pumukitgeantwebtv/images/repositories/'.$thumbUrl;
    }
}
