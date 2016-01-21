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
        else if ($multimediaObject->getProperty('redirect') === true ) {
            $this->dispatchViewEvent($multimediaObject);
            $redirectUrl = $multimediaObject->getProperty('redirect_url');
            if (!$redirectUrl) {
                throw $this->createNotFoundException();
            }
            if(!strpos('://',$redirectUrl)) {
                $redirectUrl = "http://".$redirectUrl;
            }
            return $this->redirect($redirectUrl);
        }
    }
}
