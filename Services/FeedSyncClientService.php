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
    public function getFeed($limit = 0)
    {
        $time_started = microtime(true);
        $i = 1;
        $page = 1;
        do {
            //Gets page (Exception thrown if error)
            $out = $this->getFeedPage($page, 500);
            $json = json_decode($out,true);

            //Process page. (Yields a Terena Object) TODO
            $total = $json['total'];
            foreach ($json['results'] as $jsonResult){
                $i++;
                yield $jsonResult;
            }
            $page++;
            $this->showProgressEstimateDuration ($time_started, $i, $total);
        } while(($limit == 0 || $i <= $limit) && $i <= $total);
    }

    /**
    * Returns a feed page from the feedUrl using curl
    * Throws exception if Request gives an error.
    */
    protected function getFeedPage($page, $pageSize=100){
        $url = sprintf("%s%s%s", $this->feedUrl, '?', http_build_query(array('q' => '*', 'page_size' => $pageSize, 'page' => $page), '', '&'));
        //SOLO UPV
        //$url = sprintf("%s%s%s", $this->feedUrl, '?set=UPV&',  http_build_query(array('page' => $page), '', '&'));
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
    * Prints on screen an estimated duration of the script and statistics about its execution.
    *
    */
    //TODO USE Symfony Progress Bar: http://symfony.com/doc/current/components/console/helpers/progressbar.html
    protected function showProgressEstimateDuration($time_started, $processed, $total)
    {
        $now         = microtime(true);
        $origin      = $time_started;
        $elapsed_sec = (float) ($now - $origin);
        $eta_sec     = ($total * $elapsed_sec) / $processed;
        $eta_min     = $eta_sec / 60;
        $elapsed_min = $elapsed_sec / 60;
        $processed_min = (integer) ($processed / $elapsed_min);
        echo "\nTerena entry " . $processed . " / " . $total . "\n";
        echo "Elapsed time: " . sprintf('%.2F', $elapsed_min) .
        " minutes - estimated: " . sprintf('%.2F', $eta_min) .
        " minutes. Speed: " . $processed_min . " terenas / minute.\n";
    }
}
