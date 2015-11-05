<?php
namespace Pumukit\Geant\WebTVBundle\Services;
/**
*  Service that processes the returned JSON from FeedSyncClientService into an object that can be read by the FeedSyncService.
*
*/
class FeedProcesserService
{
    private $DEF_LANG;

    public function __construct()
    {
        $this->DEF_LANG='en';
        $this->init();
    }

    public function init()
    {
        //TODO DO I NEED INITS?
    }

    public function process( $geantFeedObject )
    {
        $processedObject = array();
        if(empty($geantFeedObject['expressions'])) {
            throw new \Exception(sprintf('ERROR: The Geant Feed with ID: %s  does not have a playable resource.',$geantFeedObject['identifier']));
        }
        $processedObject['lastSyncDate'] = new \DateTime();
        $processedObject['provider'] = $geantFeedObject['set'];
        $processedObject['identifier'] = $geantFeedObject['identifier'];
        $processedObject['status'] = $geantFeedObject['status'];
        $lang = $this->retrieveLanguage($geantFeedObject);
        $processedObject['language'] = $lang;
        $processedObject['title'] = $this->retrieveTitle($geantFeedObject, $lang);
        $processedObject['description'] = $this->retrieveDescription($geantFeedObject, $lang);
        $processedObject['keywords'] = $this->retrieveKeywords($geantFeedObject, $lang);
        $processedObject['public_date'] = $this->processDateField($geantFeedObject['creationDate']);
        $processedObject['record_date'] = $this->processDateField($geantFeedObject['lastUpdateDate']);// or creationg date?
        $processedObject['copyright'] = $this->retrieveCopyright($geantFeedObject, $lang);
        $processedObject['license'] = $this->retrieveCopyright($geantFeedObject, $lang);
        $processedObject['track_url'] = $geantFeedObject['expressions']['manifestations']['items']['url'];
        if(isset($geantFeedObject['expressions']['manifestations']['format'])) {
            $format = $geantFeedObject['expressions']['manifestations']['format']; //NOTE This field should be mandatory (FCCN DOESN'T HAVE IT)
        }
        else {
            $format = '';
        }
        $processedObject['track_format'] = $format;
        if(isset($geantFeedObject['expressions']['manifestations']['duration'])) {
            $duration = $geantFeedObject['expressions']['manifestations']['duration']; //NOTE This field should be mandatory (CAMPUSDOMAR DOESN'T HAVE IT)
            if(strlen($duration)<6)
            $duration = sprintf('00:%s',$duration);
            $duration = date_parse($duration);
            $duration = $duration['hour']*3600 + $duration['minute']*60 + $duration['second'];
        }
        else {
            $duration = 0;
        }
        $processedObject['duration'] = $duration;
        $processedObject['thumbnail'] = $geantFeedObject['expressions']['manifestations']['thumbnail'];
        $processedObject['tags'] = $this->retrieveTagCodes($geantFeedObject);
        $processedObject['people'] = $this->retrievePeople($geantFeedObject);
        return $processedObject;
    }

    public function retrieveLanguage($geantFeedObject)
    {
        if(!isset($geantFeedObject['expressions']['language'])) {
            throw new \Exception(sprintf('There is no language (expressions.language) on feed with ID: %', $geantFeedObject['identifier']));
        }
        $lang = $geantFeedObject['expressions']['language'];
        $lang = substr($lang,-2,2);
        return $lang;
    }

    public function retrieveTitle($geantFeedObject, $lang)
    {
        if(isset($geantFeedObject['languageBlocks'][$this->DEF_LANG]['title'])) {
            return $geantFeedObject['languageBlocks'][$this->DEF_LANG]['title'];
        }
        else if(isset($geantFeedObject['languageBlocks'][$lang]['title'])){
            return $geantFeedObject['languageBlocks'][$lang]['title'];
        }
        else {
            foreach($geantFeedObject['languageBlocks'] as $langBlock) {
                if(isset($langBlock['title'])){
                    return $langBlock['title'];
                }
            }
        }
        throw new \Exception(sprintf('There is no languageBlocks.*.title on feed with ID: %s',$geantFeedObject['identifier']));
    }

    public function retrieveDescription($geantFeedObject, $lang)
    {
        $description = '';
        if(isset($geantFeedObject['languageBlocks'][$this->DEF_LANG]['description'])) {
            $description = $geantFeedObject['languageBlocks'][$this->DEF_LANG]['description'];
        }
        else if(isset($geantFeedObject['languageBlocks'][$lang]['description'])){
            $description = $geantFeedObject['languageBlocks'][$lang]['description'];
        }
        else {
            foreach($geantFeedObject['languageBlocks'] as $langBlock) {
                if(isset($langBlock['description'])){
                    $description = $langBlock['description'];
                }
            }
        }
        return $description;
    }

    public function retrieveCopyright($geantFeedObject, $lang)
    {
        $copyright = '';
        if(isset($geantFeedObject['rights']['description'][$this->DEF_LANG])) {
            $copyright = $geantFeedObject['rights']['description'][$this->DEF_LANG];
        }
        else if(isset($geantFeedObject['rights']['description'][$lang])){
            $copyright = $geantFeedObject['rights']['description'][$lang];
        }
        else if(isset($geantFeedObject['rights']['description'])) {
            foreach($geantFeedObject['rights']['description'] as $rights) {
                if(isset($rights)){
                    $copyright = $rights;
                }
            }
        }
        else {
            $copyright = 'NO COPYRIGHT ADDED.'; //Copyright is MANDATORY! (Prace does not have it)
        }
        return $copyright;
    }

    public function retrieveKeywords($geantFeedObject, $lang)
    {
        $keywords = array();
        foreach($geantFeedObject['languageBlocks'] as $langBlock) {
            if(isset($langBlock['keywords'])){
                $keywords = array_merge($keywords, (array)$langBlock['keywords']);
            }
        }
        return $keywords;
    }

    public function retrieveTagCodes($geantFeedObject)
    {
        $tags = array();
        if(isset($geantFeedObject['tokenBlock']['taxonPaths'])){
            foreach($geantFeedObject['tokenBlock']['taxonPaths'] as $key=>$tag) {
                $tags[] = $key;
            }
        }
    return $tags;
    }

    /**
    *
    */
    public function retrievePeople($geantFeedObject)
    {
        $people = array();
        if(isset($geantFeedObject['contributors'])) {
            foreach($geantFeedObject['contributors'] as $id=>$contributor) {
                if(!isset($contributor['name'])) {
                    continue;
                }
                $people[$id] = array();
                $people[$id]['name'] = $contributor['name'];
                $people[$id]['role'] = isset($contributor['role']) ? mb_strtolower($contributor['role']) : '';
            }
        }
        return $people;
    }

    public function processDateField($dateString)
    {
        $date = $dateString;
        return $date;
    }

    public function mapCodeToItunes($code)
    {
        $code = substr($code,0,3);
        $mapTable = array('U11' => array('108101'),
                          'U12' => array('108'),
                          'U21' => array('109101'),
                          'U22' => array('109108'),
                          'U23' => array('109104'),
                          'U24' => array('109103'),
                          'U25' => array('109102','109107'),
                          'U31' => array('109100'),
                          'U32' => array('103'),
                          'U33' => array('101'),
                          'U51' => array('110107'),
                          'U53' => array('100100'),
                          'U54' => array('109106'),
                          'U55' => array('104'),
                          'U56' => array('111'),
                          'U57' => array('106109'),
                          'U58' => array('112'),
                          'U59' => array('110101'),
                          'U61' => array('110103'),
                          'U62' => array('102'),
                          'U63' => array('110105'),
                          'U71' => array('105102'),
                          'U72' => array('105101'),
                          'U92' => array('111')
        );
        if(isset($mapTable[$code])) {
            $mappedCode = $mapTable[$code];
        }
        else {
            $mappedCode = array();
        }
        return $mappedCode;
    }
}
