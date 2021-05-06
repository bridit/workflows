<?php

namespace Bridit\Workflows\DAL\QueryBuilder;

use Formapro\Pvm\State\ArrayState;
use Traversable;
use Carbon\Carbon;
use LogicException;
use Ramsey\Uuid\Uuid;
use Formapro\Pvm\DAL;
use Formapro\Pvm\Token;
use Formapro\Pvm\Process;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Formapro\Pvm\State\StatefulInterface;
use function Formapro\Values\get_values;

class QueryBuilderDAL implements DAL
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
  protected StatefulInterface $tokenStateManager;

  /**
   * QueryBuilderDAL constructor.
   */
  public function __construct(StatefulInterface $tokenStateManager = null)
  {
    $this->tokensTableName = Config::get('workflow.dal.tokens_table_name', 'pvm_tokens');
    $this->processesTableName = Config::get('workflow.dal.processes_table_name', 'pvm_processes');
    $this->tokenStateManager = $tokenStateManager ?: new ArrayState();
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

    return $token;
  }

  /**
   * @param Token $token
   * @param string|null $id
   * @return Token
   */
  public function forkProcessToken(Token $token, string $id = null): Token
  {
    return $token;

//    $newToken = $this->createProcessToken($token->getProcess(), $id);
//
////    $token->getValue()
////    $newToken->setValue();
//
//    return $newToken;
  }

  /**
   * @param Process $process
   * @return Traversable
   */
  public function getProcessTokens(Process $process): Traversable
  {
    $tokens = DB::table($this->tokensTableName)
      ->where('process_id', $process->getId())
      ->get();

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
    $dbToken = DB::table($this->tokensTableName)
      ->where('process_id', $process->getId())
      ->where('id', $id)
      ->first();

    if ($dbToken === null) {
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

    $attributes = [
      'process_id' => $token->getProcess()->getId(),
      'id' => $token->getId(),
    ];

    DB::transaction(function () use($token, $attributes) {
      DB::table($this->tokensTableName)
        ->updateOrInsert($attributes, [
          'updated_at' => Carbon::now(),
          'state' => json_encode(get_values($token))
        ]);

      DB::commit();
    });

    $this->persistProcess($token->getProcess());

  }

  /**
   * @param Process $process
   * @return void
   */
  public function persistProcess(Process $process): void
  {

    DB::transaction(function () use($process) {
      DB::table($this->processesTableName)
        ->updateOrInsert(['id' => $process->getId()], [
          'updated_at' => Carbon::now(),
          'state' => json_encode(get_values($process))
        ]);

      DB::commit();
    });

  }

  /**
   * @param string $id
   * @return Token
   */
  public function getToken(string $id): Token
  {

    $dbToken = DB::table($this->tokensTableName)
      ->where('id', $id)
      ->first();

    if ($dbToken === null) {
      throw new LogicException(sprintf('The token with id "%s" could not be found', $id));
    }

    $dbProcess = DB::table($this->processesTableName)
      ->where('id', $dbToken->process_id)
      ->first();

    if ($dbProcess === null) {
      throw new LogicException(sprintf('The process with id "%s" could not be found', $dbToken->process_id));
    }

    $process = Process::create(json_decode($dbProcess->state, true));

    return $this->createTokenFromDb($dbToken, $process);

  }

  /**
   * @param $dbToken
   * @param $process
   * @return Token
   */
  private function createTokenFromDb($dbToken, $process): Token
  {

    $token = Token::create(json_decode($dbToken->state, true));
    $token->setId($dbToken->id);
    $token->setProcess($process);

    return $token;

  }

}