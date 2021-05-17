<?php

namespace Bridit\Workflows;

use Illuminate\Support\Arr;
use Throwable;
use Formapro\Pvm\DAL;
use Formapro\Pvm\Token;
use Formapro\Pvm\State\ObjectState;
use Illuminate\Support\Facades\Config;
use Formapro\Pvm\Visual\VisualizeFlow;
use Bridit\Workflows\ValueObjects\Response;
use Formapro\Pvm\Visual\BuildDigraphScript;
use Bridit\Workflows\DAL\DynamoDb\DynamoDbDAL;
use Bridit\Workflows\ValueObjects\TaskWorkflow;

class Service
{

  /**
   * @param \Formapro\Pvm\DAL|null $dal
   * @return \Formapro\Pvm\DAL
   */
  protected function getDal(DAL $dal = null): DAL
  {
    return $dal ?? new DynamoDbDAL(new ObjectState(), [
      'accessKeyId' => Config::get('aws.credentials.key'),
      'accessKeySecret' => Config::get('aws.credentials.secret'),
      'region' => Config::get('aws.region', 'sa-east-1'),
    ]);
  }

  /**
   * @param TaskWorkflow $workflow
   * @return ProcessBuilder
   */
  protected function getProcessBuilder(TaskWorkflow $workflow): ProcessBuilder
  {
    return (new ProcessBuilder)->import($workflow);
  }

  /**
   * @param array $values
   * @return TaskBehaviorRegistry
   */
  protected function getBehaviorRegistry(array $values = []): TaskBehaviorRegistry
  {
    return (new TaskBehaviorRegistry)
      ->setValues($values);
  }

  /**
   * @param TaskWorkflow $workflow
   * @param array $values
   * @return ProcessEngine
   */
  protected function getProcessEngine(TaskWorkflow $workflow, array $values = []): ProcessEngine
  {
    $dal = $this->getDal();

    $processBuilder = $this->getProcessBuilder($workflow);

    $registry = $this->getBehaviorRegistry($values);

    return (new ProcessEngine($registry, $dal))
      ->setProcessBuilder($processBuilder);
  }

  /**
   * @param TaskWorkflow $workflow
   * @param array $values
   * @return Response
   * @throws Throwable
   */
  public function execute(TaskWorkflow $workflow, array $values = []): Response
  {
    $engine = $this->getProcessEngine($workflow, $values);

    return $engine->execute();
  }

  /**
   * @param TaskWorkflow $workflow
   * @param array $values
   * @param string|null $tokenId
   * @param mixed $interaction
   * @return Response
   * @throws Throwable
   */
  public function continue(TaskWorkflow $workflow, array $values = [], ?string $tokenId = null, mixed $interaction = null): Response
  {
    $engine = $this->getProcessEngine($workflow, $values);

    return $engine->continue($tokenId, $interaction);
  }

  /**
   * @param Token $token
   * @return array
   */
  public function getGraph(Token $token): array
  {
    $visualizeFlow = new VisualizeFlow;
    $graph = $visualizeFlow->createGraph($token->getProcess());
    $visualizeFlow->applyTokens($graph, $token->getProcess(), [$token]);

    $tokenValues = \Formapro\Values\get_values($token);
    $processValues = \Formapro\Values\get_values($token->getProcess());

    foreach ($processValues['nodes'] as $nodeId => $nodeValue)
    {
      $processValues['nodes'][$nodeId] = array_merge($processValues['nodes'][$nodeId], Arr::get($tokenValues, "nodes.$nodeId", []));
    }

    return [
      'dot' => (new BuildDigraphScript)->build($graph),
      'process' => $processValues,
      'tokens' => [],
    ];
  }

  /**
   * @param string $id
   * @return array
   */
  public function getGraphFromTokenId(string $id): array
  {
    $dal = $this->getDal();

    $token = $dal->getToken($id);

    return $this->getGraph($token);
  }

}
