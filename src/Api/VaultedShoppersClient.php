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
    // Fetch the vaulted shopper and remove the card from their payment sources.
    $vaulted_shopper = $this->get($vaulted_shopper_id);

    // Go through all the cards of this user and if we find a matching one,
    // set the status of the card to delete.
    $payment_sources = $vaulted_shopper->paymentSources->creditCardInfo;
    $card_found = FALSE;
    foreach ($payment_sources as $key => $payment_source) {
      $card = $payment_source->creditCard;
      if (
        $card->cardLastFourDigits === $data['cardLastFourDigits']
        && $card->expirationMonth === $data['expirationMonth']
        && $card->expirationYear === $data['expirationYear']
      ) {
        $card_found = TRUE;
        $vaulted_shopper->paymentSources->creditCardInfo[$key]->status = 'D';
      }
    }

    // Just return if we don't have a matching card.
    if (!$card_found) {
      return;
    }

    // Update the vaulted shopper on BlueSnap with the deleted card.
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
