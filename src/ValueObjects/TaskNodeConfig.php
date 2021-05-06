<?php

namespace Bridit\Workflows\ValueObjects;

use Bifrost\Support\ValueObjects\ValueObject;

class TaskNodeConfig extends ValueObject
{

  public ?string $label;
  public ?string $taskName;
  public ?string $inputTransformerName;
  public ?bool $pauseExecution = false;
  public ?bool $conditional = false;
  public ?string $successNodeId;
  public ?string $failNodeId;
  public ?NodeVisual $visual;

}
