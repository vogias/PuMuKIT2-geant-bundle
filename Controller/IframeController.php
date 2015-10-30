<?php

namespace Pumukit\Geant\WebTVBundle\Controller;

use Pumukit\WebTVBundle\Controller\PlayerController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Pumukit\SchemaBundle\Document\MultimediaObject;

class IframeController extends PlayerController
{
    public function indexAction( MultimediaObject $multimediaObject, Request $request ){
        $iframeUrl = $multimediaObject->getProperty('iframe_url');
        return $this->redirect($iframeUrl);
        //TODO, instead of a redirect, it should show an iframe.
    }
}
