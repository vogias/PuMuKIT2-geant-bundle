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
        $limit = $this->container->getParameter('limit_objs_recentlyadded');
        $numberCols = $this->container->getParameter('columns_objs_announces');
        $templateTitle = $this->container->getParameter('menu.announces_title');

        $this->get('pumukit_web_tv.breadcrumbs')->addList($templateTitle, 'pumukit_webtv_announces_latestuploads');

        $announcesService = $this->get('pumukitschema.announce');
        $lastMms = $announcesService->getLast($limit);

        return array('template_title' => $templateTitle,
                     'last' => $lastMms,
                     'number_cols' => $numberCols );
    }
}
