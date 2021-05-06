<?php

namespace Bridit\Workflows\DAL\DynamoDb;

use Traversable;
use Carbon\Carbon;
use LogicException;
use Ramsey\Uuid\Uuid;
use Formapro\Pvm\DAL;
use Formapro\Pvm\Token;
use Formapro\Pvm\Process;
use Formapro\Pvm\State\ObjectState;
use AsyncAws\DynamoDb\DynamoDbClient;
use Illuminate\Support\Facades\Config;
use Formapro\Pvm\State\StatefulInterface;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Result\GetItemOutput;
use AsyncAws\DynamoDb\Input\UpdateItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use function Formapro\Values\get_values;

class DynamoDbDAL implements DAL
{

  /**
   * @var string|mixed
   */
  private string $tokensTableName;

  /**
   * @var string|mixed
   */
  private string $processesTableName;

  /**
   * @var StatefulInterface
   */
  protected StatefulInterface $stateManager;

  /**
   * @var \AsyncAws\DynamoDb\DynamoDbClient
   */
  protected DynamoDbClient $dynamoDb;

  /**
   * DynamoDbDAL constructor.
   */
  public function __construct(StatefulInterface $stateManager = null, array $config = [])
  {
    $this->dynamoDb = new DynamoDbClient($config);
    $this->tokensTableName = Config::get('workflow.dal.tokens_table_name', 'pvm_tokens');
    $this->processesTableName = Config::get('workflow.dal.processes_table_name', 'pvm_processes');
    $this->stateManager = $stateManager ?: new ObjectState();
  }

  /**
   * @param Process $process
   * @param string|null $id
   * @return Token
   */
  public function createProcessToken(Process $process, string $id = null): Token
  {
    $token = Token::create();
    $token->setId($id ?: Uuid::uuid4());
    $token->setProcess($process);
    $token->replaceState($this->stateManager);

    return $token;
  }

  /**
   * @param Token $token
   * @param string|null $id
   * @return Token
   */
  public function forkProcessToken(Token $token, string $id = null): Token
  {
    return $this->createProcessToken($token->getProcess(), $id);
  }

  /**
   * @param Process $process
   * @return Traversable
   */
  public function getProcessTokens(Process $process): Traversable
  {
    $itemInput = new GetItemInput([
      'TableName' => $this->tokensTableName,
      'ConsistentRead' => true,
      'Key' => [
        'process_id' => new AttributeValue(['S' => $process->getId()]),
      ],
    ]);

    $tokens = $this
      ->dynamoDb
      ->batchGetItem($itemInput);

    foreach ($tokens as $dbToken)
    {
      yield $this->createTokenFromDb($dbToken, $process);
    }
  }

  /**
   * @param Process $process
   * @param string $id
   * @return Token
   */
  public function getProcessToken(Process $process, string $id): Token
  {
    $dbToken = $this->findProcessToken($id, $process);

    if (null === $dbToken) {
      throw new LogicException(sprintf('The token with id "%s" could not be found', $id));
    }

    return $this->createTokenFromDb($dbToken, $process);
  }

  /**
   * @param Token $token
   * @return void
   */
  public function persistToken(Token $token): void
  {

    $updateExpressions = [
      'created_at = if_not_exists(created_at, :created_at)',
      'updated_at = :updated_at',
      'process_id = if_not_exists(process_id, :process_id)',
      'state_json = :state_json'
    ];

    $itemInput = new UpdateItemInput([
      'TableName' => $this->tokensTableName,
      'Key' => [
        'id' => new AttributeValue(['S' => $token->getId()]),
      ],
      'UpdateExpression' => 'SET ' . implode(', ', $updateExpressions),
      'ExpressionAttributeValues' => [
        ':created_at' => new AttributeValue(['S' => Carbon::now()->toIso8601String()]),
        ':process_id' => new AttributeValue(['S' => $token->getProcess()->getId()]),
        ':updated_at' => new AttributeValue(['S' => Carbon::now()->toIso8601String()]),
        ':state_json' => new AttributeValue(['S' => json_encode($token->toArray())]),
      ],
      'ReturnValues' => 'NONE',
    ]);

    $this->dynamoDb->updateItem($itemInput);

    $this->persistProcess($token->getProcess());

  }

  /**
   * @param Process $process
   * @return void
   */
  public function persistProcess(Process $process): void
  {

    $updateExpressions = [
      'created_at = if_not_exists(created_at, :created_at)',
      'updated_at = :updated_at',
      'state_json = :state_json'
    ];

    $itemInput = new UpdateItemInput([
      'TableName' => $this->processesTableName,
      'Key' => [
        'id' => new AttributeValue(['S' => $process->getId()]),
      ],
      'UpdateExpression' => 'SET ' . implode(', ', $updateExpressions),
      'ExpressionAttributeValues' => [
        ':created_at' => new AttributeValue(['S' => Carbon::now()->toIso8601String()]),
        ':updated_at' => new AttributeValue(['S' => Carbon::now()->toIso8601String()]),
        ':state_json' => new AttributeValue(['S' => json_encode(get_values($process))]),
      ],
      'ReturnValues' => 'NONE',
    ]);

    $this->dynamoDb->updateItem($itemInput);

  }

  /**
   * @param string $id
   * @return Token
   */
  public function getToken(string $id): Token
  {

    $dbToken = $this->findProcessToken($id);

    if (null === $dbToken) {
      throw new LogicException(sprintf('The token with id "%s" could not be found', $id));
    }

    $dbProcess = $this->findProcess($dbToken->getItem()['process_id']->getS());

    if (null === $dbProcess) {
      throw new LogicException(sprintf('The process with id "%s" could not be found', $dbToken->getItem()['process_id']->getS()));
    }

    $process = Process::create(json_decode($dbProcess->getItem()['state_json']->getS(), true));

    $stateJson = json_decode($dbToken->getItem()['state_json']->getS(), true);
    $state = $stateJson['state'] ?? [];
    unset($stateJson['state']);

    $this->stateManager->hydrate($state);

    $token = Token::create($stateJson);
    $token->setId($id);
    $token->setProcess($process);
    $token->replaceState($this->stateManager);

    return $token;
  }

  /**
   * @param string $id
   * @param Process|null $process
   * @return GetItemOutput|null
   */
  public function findProcessToken(string $id, Process $process = null): ?GetItemOutput
  {
    $key = ['id' => new AttributeValue(['S' => $id])];

    if (null !== $process) {
      $key['process_id'] = new AttributeValue(['S' => $process->getId()]);
    }

    $itemInput = new GetItemInput([
      'TableName' => $this->tokensTableName,
      'ConsistentRead' => true,
      'Key' => $key,
    ]);

    $dbToken = $this
      ->dynamoDb
      ->getItem($itemInput);

    return !blank($dbToken->getItem())
      ? $dbToken
      : null;

//    return $this->createTokenFromDb($dbToken, $process);
  }

  /**
   * @param string $id
   * @return GetItemOutput|null
   */
  public function findProcess(string $id): ?GetItemOutput
  {

    $itemInput = new GetItemInput([
      'TableName' => $this->processesTableName,
      'ConsistentRead' => true,
      'Key' => ['id' => new AttributeValue(['S' => $id])],
    ]);

    $result = $this
      ->dynamoDb
      ->getItem($itemInput);

    return !blank($result->getItem())
      ? $result
      : null;

  }

  /**
   * @param GetItemOutput $dbToken
   * @param $process
   * @return Token
   */
  private function createTokenFromDb(GetItemOutput $dbToken, $process): Token
  {

    $stateJson = json_decode($dbToken->getItem()['state_json']->getS(), true);
    $state = $stateJson['state'] ?? [];
    unset($stateJson['state']);

    $token = Token::create($stateJson);
    $token->setId($dbToken->getItem()['id']->getS());
    $token->setProcess($process);
    $token->setState($this->stateManager);
    $token->hydrateState($state);

    return $token;

  }

}