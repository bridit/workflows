<?php

namespace Bridit\Workflows;

use Formapro\Pvm\Process;
use Bridit\Workflows\ValueObjects\Node;
use Bridit\Workflows\ValueObjects\TaskNode;
use Bridit\Workflows\ValueObjects\Workflow;

class ProcessBuilder extends \Formapro\Pvm\ProcessBuilder
{

  /**
   * ProcessBuilder constructor.
   * @param Process|null $process
   */
  public function __construct(Process $process = null)
  {
    parent::__construct($process);

    $this->createSetupNode();
  }

  /**
   * @param Workflow $workflow
   * @return $this
   */
  public function import(Workflow $workflow): self
  {

    foreach ($workflow->nodes as $node)
    {
      $this->createTaskNode($node);
    }

    $this->createTransition('setup', $workflow->startNode)->end();

    foreach ($workflow->transitions as $transition)
    {
      $this
        ->createTransition($transition->from, $transition->to)
        ->end();
    }

    return $this;

  }

  /**
   * @return void
   */
  private function createSetupNode(): void
  {
    $this
      ->createNode('setup', 'setup')
      ->setLabel('Setup')
      ->setConfig('visual', ['type' => 'house', 'color' => 'black'])
      ->end()
      ->createStartTransition('setup')
      ->end();
  }

  /**
   * @param Node|TaskNode $nodeVO
   * @return void
   */
  private function createTaskNode(Node|TaskNode $nodeVO): void
  {
    if (true === $nodeVO->config->conditional) {
      $nodeVO->config->visual->type ??= 'gateway';
      $nodeVO->config->visual->color ??= 'orange';
    }

    $this
      ->createNode($nodeVO->id, 'execute_task')
      ->setLabel($nodeVO->config->label ?? $nodeVO->id)
      ->replaceConfig(array_merge($nodeVO->state ?? [], $nodeVO->config->toArray('snake')))
      ->end();
  }

}