<?php

namespace Drupal\commerce_exchanger\Plugin\Commerce\ExchangerProvider;

use Drupal\Component\Serialization\Json;

/**
 * Provides BlueSnap.
 *
 * @CommerceExchangerProvider(
 *   id = "bluesnap",
 *   label = "BlueSnap",
 *   display_label = "BlueSnap",
 *   base_currency = "USD",
 *   auth = TRUE,
 *   modes = TRUE
 * )
 */
class BluesnapExchanger extends ExchangerProviderRemoteBase {

  /**
   * {@inheritdoc}
   */
  public function apiUrl() {
    if ($this->getMode() === 'test') {
      return 'https://sandbox.bluesnap.com/services/2/tools/currency-rates';
    }
    return 'https://bluesnap.com/services/2/tools/currency-rates';
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteData($base_currency = NULL) {
    $external_data = [];

    $options['auth'] = [
      $this->getAuthData(),
    ];

    $options['headers'] = [
      'Accept' => 'application/json',
    ];

    $raw_response = $this->apiClient($options);
    if (!$raw_response) {
      return [];
    }

    $response = Json::decode($raw_response);
    if (empty($response['currencyRate'])) {
      $this->logger->error(
        'An error occurred while fetching exchange rates from BlueSnap @url',
        ['@url' => $this->apiUrl()]
      );
      return [];
    }

    // Loop through the api response and build the exchange rate array.
    foreach ($response['currencyRate'] as $rate) {
      $code = (string) $rate['quoteCurrency'];
      $rate = (string) $rate['conversionRate'];
      $external_data[$code] = $rate;
    }

    return $external_data;
  }

}
