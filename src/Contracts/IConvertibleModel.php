<?php

namespace Bridit\Workflows\Contracts;

interface IConvertibleModel
{
  public function executeConversion(IConverter $converter): mixed;
}
