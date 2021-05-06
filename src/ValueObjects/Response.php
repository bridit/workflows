<?php

namespace Bridit\Workflows\ValueObjects;

use Bifrost\Support\ValueObjects\ValueObject;

class Response extends ValueObject
{

  public ?string $processId;
  public ?string $tokenId;
  public ?string $nodeId;
  public ?bool $branchEnded;
  public ?array $tokens;
  public $output;

}