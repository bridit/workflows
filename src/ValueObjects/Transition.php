<?php

namespace Bridit\Workflows\ValueObjects;

use Bifrost\Support\ValueObjects\ValueObject;

class Transition extends ValueObject
{

  /**
   * @var string|null
   */
  public ?string $from;

  /**
   * @var string|null
   */
  public ?string $to;

  /**
   * @var mixed
   */
  public mixed $condition;

}
