<?php
namespace Pumukit\Geant\WebTVBundle\Controller;


use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CatalogueController extends Controller
{
    /**
     * @Route("/catalog/by_repository", name="pumukit_geant_webtv_repositorycatalogue")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $this->container->get('pumukit_webtv.breadcrumbs')->addList('Repository Catalogue','pumukit_geant_webtv_repositorycatalogue');
        $mmobjRepo = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');
        $seriesRepo = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:Series');
        $tagsRepo = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:Tag');
        $parentTag = $tagsRepo->findOneByCod('PROVIDER');
        if(!isset($parentTag)) {
            throw new NotFoundHttpException("The parent Tag ('PROVIDER') was not found. Did you execute the sync script at least once(ape geant:syncfeed:import)?");
        }
        $providerTags = $parentTag->getChildren();
        $repositories = array();
        //Workaround to display only repositories with unblocked objects.
        foreach($providerTags as $tag) {
            $providerId = $tag->getCod();
            $series = $seriesRepo->findOneBySeriesProperty('geant_provider', $providerId);
            if($series) {
                $numMmobjs = $mmobjRepo->countInSeries($series);
                if($numMmobjs > 0) {
                    $repository = array();
                    $repository['title'] = $tag->getTitle();
                    $repository['cod'] = $providerId;
                    $repository['description'] = $tag->getProperty('description');
                    $repository['image_url'] = $tag->getProperty('thumbnail_url');
                    $repository['numberMultimediaObjects'] = $numMmobjs;
                    $repositories[$providerId] = $repository;
                }
            }
        }
        //Sort by title. (It could also be done on mongo)
        usort($repositories, function($a, $b) {
            return $a['title'] > $b['title'];
        });
        return array('provider_tags' => $repositories);
    }
}
