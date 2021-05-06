<?php

namespace Bridit\Workflows\ValueObjects;

use Ramsey\Uuid\Uuid;
use Bifrost\Support\ValueObjects\ValueObject;

class TaskNode extends ValueObject
{

  public ?string $id;
  public ?TaskNodeConfig $config;
  public ?array $state;

  /**
   * @param string $value
   * @return void
   */
  public function setId(string $value): void
  {
    $this->id = $value ?? Uuid::uuid4();
  }

}
