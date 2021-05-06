<?php

namespace Bridit\Workflows;

use Exception;
use Formapro\Pvm\Node;
use Formapro\Pvm\Token;
use Formapro\Pvm\Behavior;
use Illuminate\Support\Arr;
use Formapro\Pvm\DefaultBehaviorRegistry;
use Bridit\Workflows\ValueObjects\NodeTaskResponse;

class TaskBehaviorRegistry extends DefaultBehaviorRegistry
{

  /**
   * @var array
   */
  private array $values = [];

  /**
   * TaskBehaviorRegistry constructor.
   * @param array $behaviors
   */
  public function __construct(array $behaviors = [])
  {
    parent::__construct(array_merge([
      'setup' => fn(Token $token) => $this->setup($token),
      'execute_task' => fn(Token $token) => $this->handle($token),
    ], $behaviors));
  }

  /**
   * @param string $name
   * @param callable|Behavior $behavior
   * @return $this
   */
  public function setBehaviour(string $name, $behavior): self
  {
    $this->register($name, $behavior);

    return $this;
  }

  /**
   * @param array $values
   * @return $this
   */
  public function setValues(array $values): self
  {
    $this->values = $values;

    return $this;
  }

  /**
   * @param string $key
   * @param $value
   * @return $this
   */
  public function setValue(string $key, $value): self
  {
    Arr::set($this->values, $key, $value);

    return $this;
  }

  /**
   * @param Token $token
   * @return void
   */
  protected function setup(Token $token): void
  {
    foreach ($this->values as $key => $value) {
      $token->setValue($key, $value);
    }
  }

  /**
   * @param Token $token
   * @return array|null
   * @throws Exception
   */
  protected function handle(Token $token): ?array
  {

    $lastNode = $token->getCurrentTransition()->getTransition()->getFrom();
    $lastOutput = $lastNode instanceof Node ? $token->getValue($lastNode->getId()) : null;

    $node = $token->getCurrentTransition()->getTransition()->getTo();

    $nodeTaskResponse = $this->executeTask($token, $node, $lastOutput);

    $token->setValue('nodes.' . $node->getId(), $nodeTaskResponse);

    if (false === $node->getConfig('conditional')) {
      return null;
    }

    $nextNodeId = $nodeTaskResponse->response === true
      ? $node->getConfig('success_node_id')
      : $node->getConfig('fail_node_id');

    /**
     * @var Node|null $nextNode
     */
    $nextNode = Arr::first(array_filter($token->getProcess()->getNodes(), fn(Node $item) => $item->getId() === $nextNodeId));

    if ($nextNode === null) {
      throw new Exception('Unknown transition');
    }

    $tmpTransitions = [];
    foreach ($nextNode->getProcess()->getInTransitions($nextNode) as $transition) {
      if (empty($transition->getName()) && $transition->getValue('from') === $node->getId()) {
        $tmpTransitions[] = $transition;
      }
    }

    return $tmpTransitions;

  }

  /**
   * @param \Formapro\Pvm\Token $token
   * @param \Formapro\Pvm\Node $node
   * @param mixed $input
   * @return NodeTaskResponse
   * @throws \Exception
   */
  public function executeTask(Token $token, Node $node, mixed $input): NodeTaskResponse
  {
    $config = $node->getAllConfig() ?? [];

    $taskName = Arr::get($config, 'task_name');

    if (empty($taskName) || !class_exists($taskName)) {
      throw new \Exception("Task class $taskName dont exists.");
    }

    /**
     * @var NodeTaskResponse $nodeTaskResponse
     */
    $nodeTaskResponse = (new $taskName($token->getStateObject(), $config))->handle($this->getInputValue($token, $node, $input));

    if (!blank($nodeTaskResponse->label)) {
      $token
        ->getTo()
        ->setLabel($nodeTaskResponse->label);
    }

    $token
      ->replaceState($nodeTaskResponse->state);
//      ->setState('log', Arr::set($nodeLog, 'task_response', $output));

//    $nodeLog = $token->getTo()->getState('log') ?? [];

    return $nodeTaskResponse;
  }

  /**
   * @param \Formapro\Pvm\Token $token
   * @param \Formapro\Pvm\Node $node
   * @param mixed $input
   * @return mixed
   */
  private function getInputValue(Token $token, Node $node, mixed $input): mixed
  {
    $inputTransformerName = $node->getConfig('input_transformer_name');

    if (empty($inputTransformerName) || !class_exists($inputTransformerName)) {
      return $input;
    }

//    if ($input instanceof Model) {
//      return $input->executeConversion(new $inputTransformerName($this->token));
//    }

    return (new $inputTransformerName($token))->execute($input);
  }

}
