<?php

namespace Pumukit\Geant\WebTVBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\WebTVBundle\Controller\IndexController as ParentController;

class IndexController extends ParentController
{
    /**
     * @Template()
     */
    public function recentlyaddedAction()
    {
        $limit = 3;
        if ($this->container->hasParameter('limit_objs_recentlyadded')){
            $limit = $this->container->getParameter('limit_objs_recentlyadded');
        }
        $templateTitle = 'Latest Uploads';
        if($this->container->hasParameter('menu.announces_title')) {
            $templateTitle = $this->container->getParameter('menu.announces_title');
        }
        $this->get('pumukit_web_tv.breadcrumbs')->addList($templateTitle, 'pumukit_webtv_announces_latestuploads');

        $announcesService = $this->get('pumukitschema.announce');

        $lastMms = array();
        $mmobjRepo = $this->getDoctrine()->getRepository('PumukitSchemaBundle:MultimediaObject');
        //Get last objects without errors.
        $lastMms = $mmobjRepo->findStandardBy(array(), array('public_date' => -1), $limit, 0);
        $numberCols = $this->container->getParameter('columns_objs_announces');

        return array('template_title' => $templateTitle,
                     'last' => $lastMms,
                     'number_cols' => $numberCols );
    }
}
