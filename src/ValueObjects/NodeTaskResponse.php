<?php

namespace Bridit\Workflows\ValueObjects;

use Formapro\Pvm\State\ObjectState;
use Bifrost\Support\ValueObjects\ValueObject;

class NodeTaskResponse extends ValueObject
{

  public ObjectState $state;
  public ?string $label = null;
  public mixed $response = null;

}
