<?php

namespace Drupal\commerce_bluesnap\Ipn;

use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the interface for the BlueSnap IPN handler service.
 *
 * The IPN handler facilitates the BlueSnap payment gateways to parse the
 * incoming IPN content, check the authenticity and validity of the IPN, detect
 * the IPN type, and load the corresponding entity that may need to be updated
 * as a result of the IPN.
 */
interface HandlerInterface {

  /**
   * Indicates the IPN for a successful transaction.
   */
  const IPN_TYPE_CHARGE = 'CHARGE';

  /**
   * Indicates an IPN that is relevant to a transaction.
   *
   * IPN groups is not a BlueSnap concept. We use this in the handler to
   * determine the corresponding entity that may need to be updated as a result
   * of the notification i.e. IPNs of the transaction group are associated to
   * Drupal payments.
   */
  const IPN_GROUP_TRANSACTION = 0;

  /**
   * Parses the request and returns its data as an array.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array $supported_types
   *   The IPN types supported by the payment gateway.
   *
   * @return array
   *   An associative array containing the data (content) of the request.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   If the IPN is not of a supported type.
   */
  public function parseRequestData(Request $request, array $supported_types);

  /**
   * Returns IPN type for the given IPN data.
   *
   * @param array $ipn_data
   *   The request data.
   *
   * @return string
   *   The identified IPN type.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   If no type could be determined from the IPN request data.
   */
  public function getType(array $ipn_data);

  /**
   * Returns the Drupal entity related to the IPN.
   *
   * @param array $ipn_data
   *   The request data.
   *
   * @return \Drupal\Entity\EntityInterface
   *   The Drupal entity related to the given IPN data.
   */
  public function getEntity(array $ipn_data);

}
