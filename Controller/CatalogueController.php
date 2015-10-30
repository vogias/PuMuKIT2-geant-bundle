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
        $tagsRepo = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:Tag');
        $parentTag = $tagsRepo->findOneByCod('PROVIDER');
        if(!isset($parentTag)) {
            throw new NotFoundHttpException("The parent Tag ('PROVIDER') was not found. Did you execute the sync script at least once(ape geant:syncfeed:import)?");
        }
        $providerTags = $parentTag->getChildren();
        return array('provider_tags' => $providerTags);
        /*echo "<pre>W EEEEE WEEE QWE qwe qwe qwe QWE qwe qwe</pre>";*/
    }
}
