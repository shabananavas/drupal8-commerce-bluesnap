<?php

namespace Drupal\commerce_bluesnap\Api;

use Drupal\commerce_payment\Exception\HardDeclineException;
use Bluesnap\VaultedShopper;

/**
 * Client for making requests to the Vaulted Shopper API.
 */
class VaultedShoppersClient implements VaultedShoppersClientInterface {

  /**
   * {@inheritdoc}
   */
  public function create(array $data) {
    $response = VaultedShopper::create($data);

    if ($response->succeeded()) {
      return $response->data;
    }

    throw new HardDeclineException('Unable to verify the payment method details: ' . $response->data);
  }

  /**
   * {@inheritdoc}
   */
  public function get($vaulted_shopper_id) {
    $response = VaultedShopper::get($vaulted_shopper_id);

    if ($response->failed()) {
      throw new \Exception($response->data);
    }

    return $response->data;
  }

  /**
   * {@inheritdoc}
   */
  public function addCard($vaulted_shopper_id, array $data) {
    return $this->addPaymentSources(
      $vaulted_shopper_id,
      ['creditCardInfo' => [$data]]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function addEcp($vaulted_shopper_id, array $data) {
    return $this->addPaymentSources(
      $vaulted_shopper_id,
      ['ecpDetails' => [$data]]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCard($vaulted_shopper_id, array $data) {
    $payment_sources = [
      'creditCardInfo' => [
        'key' => 'creditCard',
        'sources' => [
          [
            'cardType' => $data['cardType'],
            'cardLastFourDigits' => $data['cardLastFourDigits'],
          ],
        ],
      ],
    ];
    return $this->deletePaymentSources($vaulted_shopper_id, $payment_sources);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteEcp($vaulted_shopper_id, array $data) {
    $payment_sources = [
      'ecpDetails' => [
        'key' => 'ecp',
        'sources' => [
          [
            'accountType' => $data['accountType'],
            'publicAccountNumber' => $data['publicAccountNumber'],
            'publicRoutingNumber' => $data['publicRoutingNumber'],
          ],
        ],
      ],
    ];
    return $this->deletePaymentSources($vaulted_shopper_id, $payment_sources);
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentSources($vaulted_shopper_id, array $data) {
    // Fetch the vaulted shopper and remove the card from their payment sources.
    $vaulted_shopper = $this->get($vaulted_shopper_id);

    $remote_sources = $vaulted_shopper->paymentSources;
    $sources_found = FALSE;

    // Go through all payment sources available for the remote Vaulted Shopper
    // and mark the given ones for deletion.
    foreach ($data as $source_key => $source_group) {
      if (!isset($remote_sources->{$source_key})) {
        continue;
      }

      foreach ($remote_sources->{$source_key} as $remote_source) {
        foreach ($source_group['sources'] as $source) {
          foreach ($source as $property_key => $property_value) {
            $remote_value = $remote_source->{$source_group['key']}->{$property_key};
            if ($property_value !== $remote_value) {
              continue 2;
            }
          }

          if (isset($remote_source->status) && $remote_source->status === 'D') {
            continue;
          }

          $remote_source->status = 'D';
          $sources_found = TRUE;
        }
      }
    }

    // Just return if we don't have any matching payment sources.
    if (!$sources_found) {
      return;
    }

    // Update the vaulted shopper on BlueSnap with the updated payment sources.
    $response = VaultedShopper::update($vaulted_shopper_id, $vaulted_shopper);

    if ($response->failed()) {
      throw new \Exception($response->data);
    }

    return $response->data;
  }

  /**
   * Adds new payment sources to an existing vaulted shopper on BlueSnap.
   *
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   * @param array $payment_sources
   *   An array of payment source data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Drupal\commerce_payment\Exception\HardDeclineException
   *   When adding the payment sources fails.
   */
  protected function addPaymentSources(
    $vaulted_shopper_id,
    array $payment_sources
  ) {
    // Fetch the vaulted shopper and add the payment source details.
    $vaulted_shopper = $this->get($vaulted_shopper_id);
    $vaulted_shopper->paymentSources = $payment_sources;

    // Update the vaulted shopper on BlueSnap with the new card.
    $response = VaultedShopper::update($vaulted_shopper_id, $vaulted_shopper);

    if ($response->succeeded()) {
      return $response->data;
    }

    throw new HardDeclineException('Unable to verify the payment method details: ' . $response->data);
  }

}
