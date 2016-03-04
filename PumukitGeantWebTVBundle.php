<?php

namespace Pumukit\Geant\WebTVBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PumukitGeantWebTVBundle extends Bundle
{
  const VERSION = '1.0.2-RC1';
  public function getParent()
  {
    return 'PumukitWebTVBundle';
  }
}
