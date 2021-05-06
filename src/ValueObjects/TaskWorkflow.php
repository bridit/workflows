<?php

namespace Bridit\Workflows\ValueObjects;

use Illuminate\Support\Collection;

class TaskWorkflow extends Workflow
{

  /**
   * @param array $value
   * @return void
   */
  public function setNodes(array $value = []): void
  {
    $nodes = [];

    foreach ($value as $item)
    {
      $nodes[] = TaskNode::fromArray($item);
    }

    $this->nodes = Collection::make($nodes);
  }

}
