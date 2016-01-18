<?php

namespace Pumukit\Geant\WebTVBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PumukitGeantWebTVBundle extends Bundle
{
  public function getParent()
  {
    return 'PumukitWebTVBundle';
  }
}
