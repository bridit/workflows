<?php

namespace Bridit\Workflows;

use Throwable;
use Formapro\Pvm\Node;
use Formapro\Pvm\Token;
use Psr\Log\LoggerInterface;
use Formapro\Pvm\TokenTransition;
use Bridit\Workflows\ValueObjects\Response;

class ProcessEngine extends \Formapro\Pvm\ProcessEngine
{

  /**
   * @var Token
   */
  private Token $token;

  /**
   * @var ProcessBuilder 
   */
  private ProcessBuilder $processBuilder;

  /**
   * @var Node|null
   */
  private ?Node $startNode = null;

  /**
   * @return Token
   */
  public function getCurrentToken(): Token
  {
    return $this->token;
  }

  /**
   * @return ProcessBuilder
   */
  public function getProcessBuilder(): ProcessBuilder
  {
    return $this->processBuilder;
  }

  /**
   * @param ProcessBuilder $processBuilder
   * @return $this
   */
  public function setProcessBuilder(ProcessBuilder $processBuilder): self
  {
    $this->processBuilder = $processBuilder;

    return $this;
  }

  /**
   * @return LoggerInterface|null
   */
  public function getLogger(): ?LoggerInterface
  {
    return $this->logger;
  }

  /**
   * @param LoggerInterface|null $logger
   * @return $this
   */
  public function setLogger(?LoggerInterface $logger): self
  {
    $this->logger = $logger;

    return $this;
  }

  /**
   * @return Response
   * @throws Throwable
   */
  public function execute(): Response
  {
    $this->initToken();

    $tokens = $this->proceed($this->token, $this->logger);

    return $this->getResponse($tokens);
  }

  /**
   * @param string|null $tokenId
   * @param mixed $interaction
   * @return Response
   * @throws Throwable
   */
  public function continue(string $tokenId = null, mixed $interaction = null): Response
  {
    $this->initToken($tokenId);

    $transitions = $this->token->getTransitions();
    $currentTransition = end($transitions);

    $this->token->setCurrentTransition($currentTransition);
    
    $this->startNode = $this->token->getTo();

    if (!blank($interaction)) {
      $this->setLastInteraction($this->startNode, $interaction);
    }

    $tmpTransitions = [];

    foreach ($this->token->getProcess()->getOutTransitions($this->startNode) as $transition)
    {
      if (empty($transition->getName())) {
        $tmpTransitions[] = $transition;
      }
    }

    if (0 === count($tmpTransitions)) {
      $this->persistToken($this->token);

      return $this->getResponse([]);
    }

    $tokenTransition = TokenTransition::createFor($tmpTransitions[0], 1);
    $tokenTransition->setProcess($tmpTransitions[0]->getProcess());

    $this->token->addTransition($tokenTransition);
    $this->token->setCurrentTransition($tokenTransition);

    $tokens = $this->proceed($this->token, $this->logger);

    return $this->getResponse($tokens);
  }

  /**
   * @param string|null $tokenId
   * @return void
   */
  protected function initToken(string $tokenId = null)
  {
    $this->token = $tokenId !== null 
      ? $this->dal->getToken($tokenId)
      : $this->createTokenFor($this->processBuilder->getProcess()->getStartTransition());
  }

  /**
   * @param \Formapro\Pvm\Node $node
   * @param mixed $interaction
   */
  protected function setLastInteraction(Node $node, mixed $interaction = null): void
  {
    $this->token
      ->setValue('nodes.' . $node->getId() . '.interaction', $interaction);

    $this->token
      ->getProcess()
      ->getNode($node->getId())
      ->setLabel($node->getLabel(). '\\n' . $interaction['label']);
  }

  /**
   * @param array $tokens
   * @return Response
   */
  public function getResponse(array $tokens): Response
  {
    $node = $this->token->getTo();

    $output = null;
    $branchEnded = $this->startNode !== null && (0 === count($this->token->getProcess()->getOutTransitions($this->startNode)));

    if ($node instanceof Node) {
      $response = $this->token->getValue('nodes.' . $node->getId());
      $output = is_object($response) ? $response->response : $response;
    }

    $response = new Response();
    $response->processId = $this->token->getProcess()->getId();
    $response->tokenId = $this->token->getId();
    $response->nodeId = $node instanceof Node ? $node->getId() : null;
    $response->output = $output;
    $response->tokens = $tokens;
    $response->branchEnded = $branchEnded;

    return $response;
  }

}
