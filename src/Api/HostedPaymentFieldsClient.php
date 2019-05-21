<?php

namespace Drupal\commerce_bluesnap\Api;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Bluesnap\HostedPaymentFieldsToken;
use Psr\Log\LoggerInterface;

/**
 * Client for making requests to the Hosted Payment Fields API.
 */
class HostedPaymentFieldsClient implements HostedPaymentFieldsClientInterface {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Creates a new HostedPaymentFieldsClient object.
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
  public function createToken() {
    try {
      $data = HostedPaymentFieldsToken::create();
    }
    catch (\Exception $e) {
      throw new PaymentGatewayException($e->getMessage());
    }

    return $data['hosted_payment_fields_token'];
  }

}
