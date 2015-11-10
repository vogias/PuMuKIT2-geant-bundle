<?php
namespace Pumukit\Geant\WebTVBundle\Services;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Session\Session;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;

/**
*  Service that acts as a client to read the feeds and return a JSON structure with each Geant Feed Object.
*/
class FeedSyncClientService
{
    private $feedUrl;

    public function __construct($feedUrl)
    {
        $this->feedUrl = $feedUrl;
        $this->init();
    }
    public function init()
    {
        //TODO DO I NEED INITS?
    }

    /** Returns a Generator (iterable object) for 'Terena Objects'
    * This function's return value can be iterated over as an array of 'Terena Objects'.
    *
    * For more information, see: http://php.net/manual/en/language.generators.overview.php
    */
    public function getFeed($limit = 0, $provider = null)
    {
        $time_started = microtime(true);
        $i = 1;
        $page = 1;
        $total = $this->getFeedTotal($provider);
        if($limit > $total) {
            $limit = $total;
        }
        do {
            //Gets page (Exception thrown if error)
            $out = $this->getFeedPage($page, 1000, $provider);
            $json = json_decode($out,true);

            foreach ($json['results'] as $jsonResult){
                $i++;
                yield $jsonResult;
            }
            $page++;
        } while( $i <= $limit);
    }

    /**
    * Returns a feed page from the feedUrl using curl
    * Throws exception if Request gives an error.
    */
    protected function getFeedPage($page, $pageSize=100, $provider = null){
        if(isset($provider)) {
            $url = sprintf("%s?%s", $this->feedUrl, http_build_query(array('page' => $page,
                                                                           'page_size' => $pageSize,
                                                                           'set'=>$provider), '', '&'));
        }
        else {
            $url = sprintf("%s?%s", $this->feedUrl, http_build_query(array('q' => '*',
                                                                       'page_size' => $pageSize,
                                                                       'page' => $page), '', '&'));
        }
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $sal["content"] = curl_exec($ch);
        $sal["error"] = curl_error($ch);
        $sal["status"] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($sal["status"] !== 200) {
            $msg = "HTTP Request Failed. ". $url . "\nHTTP_CODE: " . $sal["status"] ." ". $sal["error"];
            throw new \ErrorException($msg);
        }
        curl_close($ch);
        return $sal["content"];
    }

    /**
    * Returns total from a feedUrl
    * Throws exception if Request gives an error.
    */
    public function getFeedTotal($provider = null){
        if (isset($provider)) {
            $url = sprintf("%s?%s", $this->feedUrl, http_build_query(array('page' => 1, 'page_size' => 0, 'set' => $provider), '', '&'));
        }
        else {
            $url = sprintf("%s?%s", $this->feedUrl, http_build_query(array('q' => '*', 'page_size' => 0, 'page' => 1), '', '&'));
        }
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $sal["content"] = curl_exec($ch);
        $sal["error"] = curl_error($ch);
        $sal["status"] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($sal["status"] !== 200) {
            $msg = "HTTP Request Failed. ". $url . "\nHTTP_CODE: " . $sal["status"] ." ". $sal["error"];
            throw new \ErrorException($msg);
        }
        curl_close($ch);
        $json = json_decode($sal["content"],true);
        return    $total = $json['total'];
    }
}
