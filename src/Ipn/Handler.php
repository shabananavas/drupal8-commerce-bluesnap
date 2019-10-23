<?php

namespace Drupal\commerce_bluesnap\Ipn;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Default implementation for the BlueSnap IPN handler service.
 */
class Handler implements HandlerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger for this module.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new Handler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger for the Commerce BlueSnap module.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequestAccess(Request $request, $env) {
    $client_ip = $request->getClientIp();

    $authorized_ips = [];
    if ($env === 'production') {
      $authorized_ips = self::AUTHORIZED_IPS_PRODUCTION;
    }
    else {
      $authorized_ips = self::AUTHORIZED_IPS_SANDBOX;
    }

    if (!in_array($client_ip, $authorized_ips)) {
      $message = sprintf(
        'Unauthorized attempt for IPN request from IP "%s"',
        $client_ip
      );
      $this->logger->error($message);
      throw new AccessDeniedHttpException('Unauthorized request.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function parseRequestData(Request $request, array $supported_types) {
    $data_array = [];

    $data_string = html_entity_decode($request->getContent());
    parse_str($data_string, $data_array);

    // There's something wrong if the request has no data; it shouldn't happen
    // but if it does we cannot determine the IPN type and cannot do anything
    // with it.
    if (empty($data_array)) {
      $this->logger->error('IPN request submitted with no data.');
      throw new BadRequestHttpException('No data submitted.');
    }

    // Validate that the IPN type is supported by the caller.
    $this->validateType($data_array, $supported_types);

    return $data_array;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(array $ipn_data) {
    if (empty($ipn_data['transactionType'])) {
      $message = sprintf('Cannot determine the type of received IPN.');
      $this->logger->error($message);
      throw new BadRequestHttpException('Unknown IPN type.');
    }

    return $ipn_data['transactionType'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(array $ipn_data) {
    $group_type = $this->getGroupType($ipn_data);

    if ($group_type === self::IPN_GROUP_TRANSACTION) {
      return $this->getPaymentEntity($ipn_data);
    }
  }

  /**
   * Determines the IPN group type.
   *
   * Used to determine which Drupal entity will be loaded which may be updated
   * as a reaction to the IPN.
   *
   * @param array $ipn_data
   *   The data of the IPN request.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   If the IPN is of a type unknown/unsupported by this module.
   */
  protected function getGroupType(array $ipn_data) {
    $ipn_type = $this->getType($ipn_data);
    $payment_ipn_types = [
      self::IPN_TYPE_CHARGE,
      self::IPN_TYPE_REFUND,
    ];

    if (in_array($ipn_type, $payment_ipn_types)) {
      return self::IPN_GROUP_TRANSACTION;
    }

    $message = sprintf(
      'Received an IPN of an unsupported type "%s"',
      $ipn_data['transactionType']
    );
    $this->logger->error($message);
    throw new BadRequestHttpException('Unsupported IPN type.');
  }

  /**
   * Returns the payment entity related to the IPN.
   *
   * @param array $ipn_data
   *   The request data.
   *
   * @return \Drupal\commerce_payment\entity\PaymentInterface
   *   The Drupal entity related to the given IPN data.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   If the IPN does not identifies the payment.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the payment entity could not be found e.g. no longer exists.
   */
  protected function getPaymentEntity(array $ipn_data) {
    if (empty($ipn_data['referenceNumber'])) {
      $message = sprintf(
        'Cannot determine the transaction ID for IPN of type "%s".',
        $this->getType($ipn_data)
      );
      $this->logger->error($message);
      throw new BadRequestHttpException('Transaction ID required.');
    }

    $payment = $this->entityTypeManager
      ->getStorage('commerce_payment')
      ->loadByRemoteId($ipn_data['referenceNumber']);
    if (!$payment) {
      $message = sprintf(
        'Payment with transaction ID "%s" not found for IPN of type "%s".',
        $ipn_data['referenceNumber'],
        $this->getType($ipn_data)
      );
      $this->logger->error($message);
      throw new NotFoundHttpException('Transaction could not be found.');
    }

    return $payment;
  }

  /**
   * Checks whether the IPN is one of the supported types.
   *
   * Supported types are defined by the payment gateway e.g. Card Transactions
   * and ACH/ECP transactions receive different IPNs.
   *
   * @param array $ipn_data
   *   The data of the IPN request.
   * @param array $supported_types
   *   The IPN types to validate against.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   If the IPN is not of a supported type.
   */
  public function validateType(array $ipn_data, array $supported_types) {
    $ipn_type = $this->getType($ipn_data);

    if (!in_array($ipn_type, $supported_types)) {
      $message = sprintf(
        'IPN of type "%s" is not supported by the payment gateway.',
        $ipn_type
      );
      $this->logger->error($message);
      throw new BadRequestHttpException('Unsupported IPN type.');
    }
  }

  /**
   * Checks whether the IPN is for the intended payment gateway.
   *
   * @param array $ipn_data
   *   The request data.
   * @param string $payment_method_name
   *   The expected payment method name that should be in the IPN.
   *
   * @return bool
   *   Returns TRUE if the IPN is for the intended gateway.
   */
  public function ipnIsForGateway($ipn_data, $payment_method_name) {
    if ($ipn_data['paymentMethod'] === $payment_method_name) {
      return TRUE;
    }

    return FALSE;
  }

}
