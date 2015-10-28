<?php
namespace Pumukit\Geant\WebTVBundle\Services;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Session\Session;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;

/**
*  Service that iterates over the FeedSyncClientService responses, it processes them using the FeedProcesserService and then inserts/updates the object into the database.
*
*/
class FeedSyncService
{
    private $session;
    private $router;
    private $feedClientService;
    private $feedProcesserService;

    public function __construct(Router $router, Session $session, FeedSyncClientService $feedClientService, FeedProcesserService $feedProcesserService)
    {
        $this->session = $session;
        $this->router = $router;
        $this->feedClientService = $feedClientService;
        $this->feedProcesserService = $feedProcesserService;
        $this->init();
    }
    public function init()
    {
        //TODO DO I NEED INITS?
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
                echo "$e";
                continue;
            }
            
        }
    }
}
