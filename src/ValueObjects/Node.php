<?php

namespace Bridit\Workflows\ValueObjects;

use Ramsey\Uuid\Uuid;
use Bifrost\Support\ValueObjects\ValueObject;

class Node extends ValueObject
{

  public ?string $id;
  public $config;
  public $state;

  /**
   * @param string $value
   * @return void
   */
  public function setId(string $value): void
  {
    $this->id = $value ?? Uuid::uuid4();
  }

}
