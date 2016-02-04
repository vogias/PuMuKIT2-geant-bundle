<?php

namespace Pumukit\Geant\WebTVBundle\Controller;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AnnouncesController extends Controller
{
    /**
     * @Route("/latestuploads", name="pumukit_webtv_announces_latestuploads")
     * @Template()
     */
    public function latestUploadsAction(Request $request)
    {
        $limit = 20;
        $templateTitle = 'Latest Uploads';
        if($this->container->hasParameter('menu.announces_title')) {
            $templateTitle = $this->container->getParameter('menu.announces_title');
        }
        $this->get('pumukit_web_tv.breadcrumbs')->addList($templateTitle, 'pumukit_webtv_announces_latestuploads');

        $announcesService = $this->get('pumukitschema.announce');

        $lastMms = array();
        $mmobjRepo = $this->getDoctrine()->getRepository('PumukitSchemaBundle:MultimediaObject');
        //Get last objects without errors.
        $lastMms = $mmobjRepo->findStandardBy(array('properties.geant_errors' => null), array('public_date' => -1), $limit, 0);
        $numberCols = $this->container->getParameter('columns_objs_announces');

        return array('template_title' => $templateTitle,
                     'last' => $lastMms,
                     'number_cols' => $numberCols );
    }
}
