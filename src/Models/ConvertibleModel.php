<?php

namespace Bridit\Workflows\Models;

use Bridit\Workflows\Contracts\IConverter;

trait ConvertibleModel
{

  public function executeConversion(IConverter $converter): mixed
  {
    return call_user_func_array([$converter, 'from' . class_basename($this)], [$this]);
  }

}
