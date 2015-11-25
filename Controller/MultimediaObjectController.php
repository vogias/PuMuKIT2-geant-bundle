<?php
namespace Pumukit\Geant\WebTVBundle\Controller;

use Pumukit\WebTVBundle\Controller\MultimediaObjectController as ParentController;
use Symfony\Component\HttpFoundation\Request;
use Pumukit\SchemaBundle\Document\MultimediaObject;


class MultimediaObjectController extends ParentController
{
    public function preExecute(MultimediaObject $multimediaObject, Request $request)
    {
        if ($multimediaObject->getProperty('iframeable') === true ) {
            $this->dispatchViewEvent($multimediaObject);
            return $this->forward('PumukitGeantWebTVBundle:Iframe:index', array('request' => $request, 'multimediaObject' => $multimediaObject));
        }
    }
}
