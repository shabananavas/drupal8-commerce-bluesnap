<?php

namespace Drupal\commerce_bluesnap\Api;

use Drupal\commerce_payment\Exception\HardDeclineException;

use Bluesnap\CardTransaction;
use Bluesnap\Refund;
use Psr\Log\LoggerInterface;

/**
 * Client for making requests to the Card/Wallet Transactions API.
 */
class TransactionsClient implements TransactionsClientInterface {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Creates a new TransactionsClient object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function create(array $data) {
    $response = CardTransaction::create($data);

    // Return the data on success.
    if ($response->succeeded()) {
      return $response->data;
    }

    // Throw a decline exception on error. It will be handled by the
    // PaymentProcess checkout pane.
    $message = sprintf(
      'Could not process the "%s" action for the transaction. Message "%s"',
      $data['cardTransactionType'],
      $response->data
    );
    $this->logger->warning($message);
    throw new HardDeclineException('We encountered an error processing your payment method. Please verify your details and try again.');
  }

  /**
   * {@inheritdoc}
   */
  public function update($transaction_id, array $data) {
    $response = CardTransaction::update($transaction_id, $data);

    // Return the data on success.
    if ($response->succeeded()) {
      return $response->data;
    }

    // Throw a decline exception on error. It will be handled by the
    // PaymentProcess checkout pane.
    $message = sprintf(
      'Could not process the "%s" action for the transaction. Message "%s"',
      $data['cardTransactionType'],
      $response->data
    );
    $this->logger->warning($message);
    throw new HardDeclineException('We encountered an error processing your payment method. Please verify your details and try again.');
  }

  /**
   * {@inheritdoc}
   */
  public function refund($transaction_id, array $data) {
    $response = Refund::update($transaction_id, $data);

    // Return the data on success.
    if ($response->succeeded()) {
      return $response->data;
    }

    // Throw a decline exception on error.
    $message = sprintf(
      'Could not process the refund action for the transaction "%s"',
      $transaction_id
    );
    $this->logger->warning($message);
    throw new HardDeclineException('
      We encountered an error processing the refund. Please try again later.'
    );
  }

}
