<?php

namespace Drupal\commerce_bluesnap\Api;

/**
 * Defines the interface for all Vaulted Shoppers API clients.
 */
interface VaultedShoppersClientInterface extends ClientInterface {

  /**
   * The identifier for the Vaulted Shopper API.
   */
  const API_ID = 'vaulted-shoppers';

  /**
   * Creates a new vaulted shopper on the BlueSnap gateway.
   *
   * @param array $data
   *   An array of shopper data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Drupal\commerce_payment\Exception\HardDeclineException
   *   When creating the vaulted shopper fails.
   */
  public function create(array $data);

  /**
   * Fetch an existing vaulted shopper from the BlueSnap gateway.
   *
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Exception
   *   When getting the vaulted shopper fails.
   */
  public function get($vaulted_shopper_id);

  /**
   * Adds a new card to an existing vaulted shopper on the BlueSnap gateway.
   *
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   * @param array $data
   *   An array of card data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Drupal\commerce_payment\Exception\HardDeclineException
   *   When adding the card fails.
   */
  public function addCard($vaulted_shopper_id, array $data);

  /**
   * Adds a new ECP to an existing vaulted shopper on the BlueSnap gateway.
   *
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   * @param array $data
   *   An array of ECP data to pass to BlueSnap.
   *
   * @return array
   *   The response returned from BlueSnap.
   *
   * @throws \Drupal\commerce_payment\Exception\HardDeclineException
   *   When adding the ECP fails.
   */
  public function addEcp($vaulted_shopper_id, array $data);

  /**
   * Deletes a card from an existing vaulted shopper on the BlueSnap gateway.
   *
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   * @param array $data
   *   An array with the details of the Card payment source that will be
   *   deleted. Keys required;
   *   - cardType
   *   - cardLastFourDigits
   *
   * @return mixed
   *   The response returned from BlueSnap, if it was a success.
   *
   * @throws \Exception
   *   When deleting the payment source fails.
   */
  public function deleteCard($vaulted_shopper_id, array $data);

  /**
   * Deletes a ECP payment source from a vaulted shopper.
   *
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   * @param array $data
   *   An array with the details of the ECP payment source that will be
   *   deleted. Keys required:
   *   - accountType
   *   - publicAccountNumber
   *   - publicRoutingNumber
   *
   * @return mixed
   *   The response returned from BlueSnap, if it was a success.
   *
   * @throws \Exception
   *   When deleting the payment source fails.
   */
  public function deleteEcp($vaulted_shopper_id, array $data);

  /**
   * Deletes one or more payment sources from a vaulted shopper.
   *
   * @param int $vaulted_shopper_id
   *   The vaulted shopper ID.
   * @param array $data
   *   An array with the details of the payment sources that will be deleted.
   *   Example data structure:
   *   [
   *     'creditCardInfo' => [
   *       'key' => 'creditCard',
   *       'sources' => [
   *         [
   *           'cardType' => 'VISA',
   *           'cardLastFourDigits' => '1234',
   *         ],
   *         [
   *           'cardType' => 'VISA',
   *           'cardLastFourDigits' => '5678',
   *         ],
   *       ],
   *     ],
   *     'ecpDetails' => [
   *       'key' => 'ecp',
   *       'sources' => [
   *         [
   *           'accountType' => 'CONSUMER_CHECKINGS',
   *           'publicAccountNumber' => '12345',
   *           'publicRoutingNumber' => '56789',
   *         ],
   *       ],
   *     ],
   *   ]
   *
   * @return mixed
   *   The response returned from BlueSnap, if it was a success.
   *
   * @throws \Exception
   *   When deleting the payment source fails.
   */
  public function deletePaymentSources($vaulted_shopper_id, array $data);

}
