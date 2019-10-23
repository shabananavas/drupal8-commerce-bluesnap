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
   * Authorized IP addresses used by BlueSnap for the production environment.
   */
  const AUTHORIZED_IPS_PRODUCTION = [
    '209.128.93.254',
    '209.128.93.98',
    '141.226.140.100',
    '141.226.141.100',
    '141.226.142.100',
    '141.226.143.100',
  ];

  /**
   * Authorized IP addresses used by BlueSnap for the sandbox environment.
   */
  const AUTHORIZED_IPS_SANDBOX = [
    '209.128.93.232',
    '141.226.140.200',
    '141.226.141.200',
    '141.226.142.200',
    '141.226.143.200',
  ];

  /**
   * Indicates the IPN for a successful transaction.
   */
  const IPN_TYPE_CHARGE = 'CHARGE';

  /**
   * Indicates the IPN for a refund on a transaction.
   */
  const IPN_TYPE_REFUND = 'REFUND';

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
   * Access control for the request.
   *
   * Validates whether this is a legitimate request made by BlueSnap.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $env
   *   The BlueSnap environment of the payment gateway.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If the IPN is unauthorized.
   */
  public function checkRequestAccess(Request $request, $env);

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

  /**
   * Returns TRUE if the IPN is for the intended payment gateway.
   *
   * @param array $ipn_data
   *   The request data.
   * @param string $payment_method_name
   *   The expected payment method name that should be in the IPN.
   *
   * @return bool
   *   Returns TRUE if the IPN is for the intended gateway.
   */
  public function ipnIsForGateway($ipn_data, $payment_method_name);

}
