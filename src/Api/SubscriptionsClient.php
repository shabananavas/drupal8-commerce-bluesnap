<?php

namespace Drupal\commerce_bluesnap\Api;

use Drupal\commerce_payment\Exception\HardDeclineException;

use Bluesnap\MerchantManagedSubscription;
use Bluesnap\MerchantManagedSubscriptionCharge;
use Psr\Log\LoggerInterface;

/**
 * Client for making requests to the Card/Wallet Transactions API.
 */
class SubscriptionsClient implements SubscriptionsClientInterface {

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
    $response = MerchantManagedSubscription::create($data);

    // Return the data on success.
    if ($response->succeeded()) {
      return $response->data;
    }

    // Throw a decline exception on error. It will be handled by the
    // PaymentProcess checkout pane.
    $message = sprintf(
      'Could not process the "%s" action for the transaction. Message "%s"',
      'merchant managed subscription create transaction',
      $response->data
    );
    $this->logger->warning($message);

    throw new HardDeclineException('We encountered an error processing your payment method. Please verify your details and try again.');
  }

  /**
   * {@inheritdoc}
   */
  public function createCharge(array $data) {
    $response = MerchantManagedSubscriptionCharge::create($data);

    // Return the data on success.
    if ($response->succeeded()) {
      return $response->data;
    }

    // Throw a decline exception on error. It will be handled by the
    // PaymentProcess checkout pane.
    $message = sprintf(
      'Could not process the "%s" action for the transaction. Message "%s"',
      'merchant managed subscription create transaction',
      $response->data
    );
    $this->logger->warning($message);

    throw new HardDeclineException('We encountered an error processing your payment method. Please verify your details and try again.');
  }

}
