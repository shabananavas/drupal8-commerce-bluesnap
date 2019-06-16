<?php

namespace Drupal\commerce_bluesnap\Api;

use Bluesnap\Bluesnap;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating and configuring BlueSnap API clients.
 */
class ClientFactory {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Creates a new ClientFactory object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Returns the configured client for the requested API.
   *
   * @param string $api
   *   The API to prepare the client for.
   * @param array $config
   *   See \Drupal\commerce_bluesnap\Api\ClientFactory::init() for details.
   *
   * @return \Drupal\commerce_bluesnap\Api\ClientInterface
   *   The configured API client.
   *
   * @throws \InvalidArgumentException
   *   When the client for an unsupported API is requested.
   */
  public function get($api, array $config) {
    $this->init($config);
    switch ($api) {
      case HostedPaymentFieldsClientInterface::API_ID:
        return new HostedPaymentFieldsClient($this->logger);

      case TransactionsClientInterface::API_ID:
        return new TransactionsClient($this->logger);

      case VaultedShoppersClientInterface::API_ID:
        return new VaultedShoppersClient($this->logger);

      case AltTransactionsClientInterface::API_ID:
        return new AltTransactionsClient($this->logger);

      case SubscriptionClientInterface::API_ID:
        return new SubscriptionClient($this->logger);

      case SubscriptionChargeClientInterface::API_ID:
        return new SubscriptionChargeClient($this->logger);

      default:
        throw new \InvalidArgumentException(
          sprintf('Unsupported API "%s"', $api)
        );
    }
  }

  /**
   * Initializes the BlueSnap PHP SDK.
   *
   * @param array $config
   *   An array containing the configuration for initializing the API client.
   *   - env: The BlueSnap environment i.e. production or sandbox.
   *   - username: The BlueSnap account username.
   *   - password: The BlueSnap account password.
   *
   * @throws \InvalidArgumentException
   *   When mandatory configuration settings are missing.
   */
  protected function init(array $config) {
    $mandatory_keys = ['env', 'username', 'password'];
    $missing_keys = array_diff($mandatory_keys, array_keys($config));
    if ($missing_keys) {
      throw new \InvalidArgumentException(
        sprintf(
          'The following configuration settings are required in order to make a call to the BlueSnap API: %s',
          explode(', ', $missing_keys)
        )
      );
    }

    Bluesnap::init(
      $config['env'],
      $config['username'],
      $config['password']
    );
  }

}
