<?php

namespace Drupal\commerce_bluesnap_currency\EventSubscriber;

use Drupal\commerce_currency_resolver\ExchangeRateEventSubscriberBase;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class ExchangeRateBluesnap.
 *
 * Fetches all currency conversion rates from bluesnap currency rate API.
 */
class ExchangeRateBluesnap extends ExchangeRateEventSubscriberBase {

  /**
   * The commerce currency resolver config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Creates a new ExchangeRateBluesnap object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('commerce_currency_resolver.currency_conversion');
  }

  /**
   * {@inheritdoc}
   */
  public static function apiUrl($mode = NULL) {
    if ($mode == "sandbox") {
      return 'https://sandbox.bluesnap.com/services/2/tools/currency-rates';
    }
    return 'https://bluesnap.com/services/2/tools/currency-rates';
  }

  /**
   * {@inheritdoc}
   */
  public static function sourceId() {
    return 'exchange_rate_bluesnap';
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalData($base_currency = NULL) {
    $external_data = [];
    // Prepare for client.
    $url = self::apiUrl(
      $this->config->get('bluesnap')['mode']
    );
    $method = 'GET';
    $options['auth'] = [
      $this->config->get('bluesnap')['username'],
      $this->config->get('bluesnap')['password'],
    ];
    $options['headers'] = [
      'Accept' => 'application/json',
    ];

    if ($this->apiClient($method, $url, $options)) {
      $response = Json::decode($this->apiClient($method, $url, $options));
      if (!empty($response['currencyRate'])) {
        // Loop through the api response and build the exchange rate array.
        foreach ($response['currencyRate'] as $rate) {
          $code = (string) $rate['quoteCurrency'];
          $rate = (string) $rate['conversionRate'];
          $external_data[$code] = $rate;
        }
      }
    }
    return $external_data;
  }

  /**
   * {@inheritdoc}
   */
  public function processCurrencies() {
    $exchange_rates = [];
    $data = $this->getExternalData();

    if ($data) {
      // Bluesnap provides all currency conversion rates with USD as the
      // base currency. Hence we need to recalculate other currencies.
      $exchange_rates = $this->crossSyncCalculate('USD', $data);
    }
    return $exchange_rates;
  }

}
