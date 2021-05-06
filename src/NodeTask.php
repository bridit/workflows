<?php

namespace Bridit\Workflows;

use Formapro\Pvm\State\StatefulInterface;
use Bridit\Workflows\ValueObjects\NodeTaskResponse;

abstract class NodeTask
{

  /**
   * @var array
   */
  protected array $config;

  /**
   * @var StatefulInterface
   */
  protected StatefulInterface $state;

  /**
   * @var string|null
   */
  protected ?string $label = null;

  /**
   * NodeTask constructor.
   * @param \Formapro\Pvm\State\StatefulInterface $state
   * @param array $config
   */
  public function __construct(StatefulInterface $state, array $config = [])
  {
    $this->state = $state;
    $this->config = $config;
  }

  /**
   * @param string $label
   */
  protected function setLabel(string $label): void
  {
    $this->label = $label;
  }

  /**
   * @param string $key
   * @param mixed $value
   */
  protected function setState(string $key, mixed $value): void
  {
    $this->state->setValue($key, $value);
  }

  /**
   * @param mixed|null $input
   * @return \Bridit\Workflows\ValueObjects\NodeTaskResponse
   */
  public function handle(mixed $input = null): NodeTaskResponse
  {
    $response = $this->execute($input);

    return new NodeTaskResponse(label: $this->label, state: $this->state, response: $response);
  }

  /**
   * @param mixed|null $input
   * @return mixed
   */
  abstract public function execute(mixed $input = null);

}
