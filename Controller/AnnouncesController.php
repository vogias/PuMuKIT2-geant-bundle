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
        $templateTitle = $this->container->getParameter('menu.announces_title');
        $numberCols = $this->container->getParameter('columns_objs_announces');

        $this->get('pumukit_web_tv.breadcrumbs')->addList($templateTitle, 'pumukit_webtv_announces_latestuploads');

        $announcesService = $this->get('pumukitschema.announce');
        $lastMms = $announcesService->getLast($limit);

        return array('template_title' => $templateTitle,
                     'last' => $lastMms,
                     'number_cols' => $numberCols );
    }
}
