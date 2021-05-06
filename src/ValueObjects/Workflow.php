<?php

namespace Bridit\Workflows\ValueObjects;

use Illuminate\Support\Collection;
use Bifrost\Support\ValueObjects\ValueObject;

class Workflow extends ValueObject
{

  public ?string $startNode;
  public Collection $nodes;
  public Collection $transitions;

  /**
   * @param array $value
   * @return void
   */
  public function setNodes(array $value = []): void
  {
    $nodes = [];

    foreach ($value as $item)
    {
      $nodes[] = Node::fromArray($item);
    }

    $this->nodes = Collection::make($nodes);
  }

  /**
   * @param array $value
   * @return void
   */
  public function setTransitions(array $value = []): void
  {
    $transitions = [];

    foreach ($value as $item)
    {
      $transitions[] = Transition::fromArray($item);
    }

    $this->transitions = Collection::make($transitions);
  }

}
