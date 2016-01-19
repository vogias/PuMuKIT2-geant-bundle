<?php

namespace Pumukit\Geant\WebTVBundle\Services;

use Symfony\Component\Intl\Intl;

/**
 *  Service that processes the returned JSON from FeedSyncClientService into an object that can be read by the FeedSyncService.
 */
class FeedProcesserService
{
    private $DEF_LANG;

    public function __construct()
    {
        $this->DEF_LANG = 'en';
        $this->init();
    }

    private function init()
    {
        //TODO DO I NEED INITS?
    }

    public function process($geantFeedObject)
    {
        $processedObject = array('geantErrors' => array());
        if (empty($geantFeedObject['expressions'])) {
            throw new FeedSyncException(sprintf('ERROR: The Geant Feed with ID: %s  does not have a playable resource.', $geantFeedObject['identifier']));
        }

        try {
            $date = $this->retrieveDate($geantFeedObject);
        } catch (FeedSyncException $e) {
            $date = new \DateTime('1/1/1970Z01:00:00Z');
            $processedObject['geantErrors']['date'] = $e->getMessage();
        }

        $lang = $this->retrieveLanguage($geantFeedObject);

        $processedObject['lastUpdateDate'] = $this->processDateField($geantFeedObject['lastUpdateDate'], $geantFeedObject);
        $processedObject['provider'] = $geantFeedObject['set'];
        $processedObject['identifier'] = $geantFeedObject['identifier'];
        $processedObject['status'] = $geantFeedObject['status'];
        $processedObject['language'] = $lang;
        $processedObject['title'] = $this->retrieveTitle($geantFeedObject, $lang);
        $processedObject['description'] = $this->retrieveDescription($geantFeedObject, $lang);
        $processedObject['keywords'] = $this->retrieveKeywords($geantFeedObject, $lang);
        $processedObject['public_date'] = $date;
        $processedObject['record_date'] = $date;
        $processedObject['copyright'] = $this->retrieveCopyright($geantFeedObject, $lang);
        $processedObject['license'] = $this->retrieveCopyright($geantFeedObject, $lang);
        $processedObject['track_url'] = isset($geantFeedObject['expressions']['manifestations']['items']['url']) ? $geantFeedObject['expressions']['manifestations']['items']['url'] : '';

        if ($processedObject['track_url'] == '') {
            throw new FeedSyncException(sprintf('The object with identifier: %s does not have an url (expressions/manifestations/items/url).', $processedObject['identifier']));
        }
        if (isset($geantFeedObject['expressions']['manifestations']['format'])) {
            $format = $geantFeedObject['expressions']['manifestations']['format']; //NOTE This field should be mandatory (FCCN DOESN'T HAVE IT)
        } else {
            $format = '';
        }
        $processedObject['track_format'] = $format;
        if (isset($geantFeedObject['expressions']['manifestations']['duration'])) {
            $duration = $geantFeedObject['expressions']['manifestations']['duration']; //NOTE This field should be mandatory (CAMPUSDOMAR DOESN'T HAVE IT)
            if (strlen($duration) < 6) {
                $duration = sprintf('00:%s', $duration);
            }
            $duration = date_parse($duration);
            $duration = $duration['hour'] * 3600 + $duration['minute'] * 60 + $duration['second'];
        } else {
            $duration = 0;
        }
        $processedObject['duration'] = $duration;
        $processedObject['thumbnail'] = $geantFeedObject['expressions']['manifestations']['thumbnail'];
        $processedObject['tags'] = $this->retrieveTagCodes($geantFeedObject);
        $processedObject['people'] = $this->retrievePeople($geantFeedObject);

        return $processedObject;
    }

    private function retrieveLanguage($geantFeedObject)
    {
        if (!isset($geantFeedObject['expressions']['language'])) {
            throw new FeedSyncException(sprintf('There is no language (expressions.language) on feed with ID: %s', $geantFeedObject['identifier']));
        }
        $lang = $geantFeedObject['expressions']['language'];
        $lang = substr($lang, -2, 2);
        $lang = strtolower($lang);
        $languageNames = Intl::getLanguageBundle()->getLanguageNames();

        if (!array_key_exists($lang, $languageNames)) {
            throw new FeedSyncException(sprintf('The feed with ID: %s has a language format that is not recognized: %s', $geantFeedObject['identifier'], $geantFeedObject['expressions']['language']));
        }

        return $lang;
    }

    private function retrieveDate($geantFeedObject)
    {
        $date = null;
        //if it's just one person...
        if (isset($geantFeedObject['contributors']['date'])) {
            $date = $geantFeedObject['contributors']['date'];
        }
        //If it's more than one...
        else {
            foreach ($geantFeedObject['contributors'] as $person) {
                if (isset($person['date'])) {
                    $date = $person['date'];
                    break;
                }
            }
        }
        //If we couldn't find the date after all...
        if (!isset($date)) {
            throw new FeedSyncException(sprintf('The feed with ID: %s does not have a "date" field', $geantFeedObject['identifier']));
        }

        return $this->processDateField($date, $geantFeedObject);
    }

    private function retrieveTitle($geantFeedObject, $lang)
    {
        if (isset($geantFeedObject['languageBlocks'][$this->DEF_LANG]['title'])) {
            return $geantFeedObject['languageBlocks'][$this->DEF_LANG]['title'];
        } elseif (isset($geantFeedObject['languageBlocks'][$lang]['title'])) {
            return $geantFeedObject['languageBlocks'][$lang]['title'];
        } else {
            foreach ($geantFeedObject['languageBlocks'] as $langBlock) {
                if (isset($langBlock['title'])) {
                    return $langBlock['title'];
                }
            }
        }
        throw new FeedSyncException(sprintf('There is no languageBlocks.*.title on feed with ID: %s', $geantFeedObject['identifier']));
    }

    private function retrieveDescription($geantFeedObject, $lang)
    {
        $description = '';
        if (isset($geantFeedObject['languageBlocks'][$this->DEF_LANG]['description'])) {
            $description = $geantFeedObject['languageBlocks'][$this->DEF_LANG]['description'];
        } elseif (isset($geantFeedObject['languageBlocks'][$lang]['description'])) {
            $description = $geantFeedObject['languageBlocks'][$lang]['description'];
        } else {
            foreach ($geantFeedObject['languageBlocks'] as $langBlock) {
                if (isset($langBlock['description'])) {
                    $description = $langBlock['description'];
                }
            }
        }

        return $description;
    }

    private function retrieveCopyright($geantFeedObject, $lang)
    {
        $copyright = '';
        if (isset($geantFeedObject['rights']['description'][$this->DEF_LANG])) {
            $copyright = $geantFeedObject['rights']['description'][$this->DEF_LANG];
        } elseif (isset($geantFeedObject['rights']['description'][$lang])) {
            $copyright = $geantFeedObject['rights']['description'][$lang];
        } elseif (isset($geantFeedObject['rights']['description'])) {
            foreach ($geantFeedObject['rights']['description'] as $rights) {
                if (isset($rights)) {
                    $copyright = $rights;
                }
            }
        } else {
            $copyright = 'NO COPYRIGHT ADDED.'; //Copyright is MANDATORY! (Prace does not have it)
        }

        return $copyright;
    }

    private function retrieveKeywords($geantFeedObject, $lang)
    {
        $keywords = array();
        foreach ($geantFeedObject['languageBlocks'] as $langBlock) {
            if (isset($langBlock['keywords'])) {
                $keywords = array_merge($keywords, (array) $langBlock['keywords']);
            }
        }

        return $keywords;
    }

    private function retrieveTagCodes($geantFeedObject)
    {
        $tags = array();
        if (isset($geantFeedObject['tokenBlock']['taxonPaths'])) {
            foreach ($geantFeedObject['tokenBlock']['taxonPaths'] as $key => $tag) {
                $tags[] = $key;
            }
        }

        return $tags;
    }

    /**
     *
     */
    private function retrievePeople($geantFeedObject)
    {
        $people = array();
        if (isset($geantFeedObject['contributors'])) {
            if (isset($geantFeedObject['contributors']['name'])) {
                $people[0]['name'] = $geantFeedObject['contributors']['name'];
                $people[0]['role'] = isset($geantFeedObject['contributors']['role']) ? mb_strtolower($geantFeedObject['contributors']['role']) : '';

                return $people;
            }
            foreach ($geantFeedObject['contributors'] as $id => $contributor) {
                if (!isset($contributor['name'])) {
                    continue;
                }
                $people[$id] = array();
                $people[$id]['name'] = $contributor['name'];
                $people[$id]['role'] = isset($contributor['role']) ? mb_strtolower($contributor['role']) : '';
            }
        }

        return $people;
    }

    private function processDateField($dateString, $geantFeedObject)
    {
        try {
            $date = new \DateTime($dateString);
        } catch (\Exception $e) {
            throw new FeedSyncException('The date: '.$dateString.' from the geant feed object id:'.$geantFeedObject['identifier'].'Could not be parsed');
        }

        return $date;
    }

    public function mapCodeToItunes($code)
    {
        $code = substr($code, 0, 3);
        $mapTable = array('U11' => array('114105', '108'),
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
                          'U56' => array('110100'),
                          'U57' => array('106109'),
                          'U58' => array('112'),
                          'U59' => array('110101'),
                          'U61' => array('110103'),
                          'U62' => array('102','107'),
                          'U63' => array('110105'),
                          'U71' => array('114102'),
                          'U72' => array('114'),
                          'U92' => array('111'),
        );
        if (isset($mapTable[$code])) {
            $mappedCode = $mapTable[$code];
        } else {
            $mappedCode = array();
        }

        return $mappedCode;
    }

    /**
     * Returns false if $url is not a valid youtube url. Otherwise returns int.
     */
    private function isYoutubeUrl($url)
    {
        return preg_match('/youtu\.be\/((\w|\-)*)/', $url) ||
               preg_match('/youtube.*(\&|\?)v\=(\w*)/', $url);
    }

    public function getEmbedUrl($url)
    {
        $embedUrl = $this->getYoutubeEmbedUrl($url);
        if (!$embedUrl) {
            $embedUrl = $this->getUnedEmbedUrl($url);
        }

        return $embedUrl;
    }

    /**
     * Returns the embedded url for a youtube video given its url. If it can't parse the youtube id, it returns false.
     */
    private function getYoutubeEmbedUrl($url)
    {
        $embedUrl = 'https://www.youtube.com/embed/';
        if (strpos($url, 'youtu.be')) {
            preg_match('~youtu\.be/((\w|\-)*)~', $url, $matches);
            if (!isset($matches[1])) {
                return false;
            }
            $embedUrl .= $matches[1];
        } else {
            preg_match('/youtube.*(\&|\?)v\=(\w*)/', $url, $matches);
            if (!isset($matches[2])) {
                return false;
            }
            $embedUrl .= $matches[2];
        }

        return $embedUrl;
    }

    /**
     * Returns the embedded url for a canaluned video given its url. If it can't parse the uned mmobj id, it returns false.
     */
    private function getUnedEmbedUrl($url)
    {
        $embedUrl = 'https://canal.uned.es/mmobj/iframe/id/';
        $canalUnedUrl = 'https://canal.uned.es/mmobj/index/id/';
        if (strpos($canalUnedUrl, $url)) {
            $embedUrl .= substr($url, strlen($canalUnedUrl));
        } else {
            $embedUrl = false;
        }

        return $embedUrl;
    }
}
