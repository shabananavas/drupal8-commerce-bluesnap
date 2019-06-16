<?php

namespace Drupal\commerce_bluesnap\EnhancedDataLevel;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Interface for the service that prepares enhanced data for card transactions.
 *
 * Bluesnap's enhanced data levels, such as Level 2 and Level 3, require extra
 * information to process transactions. This service provides the methods for
 * preparing the additional data that will be sent with the transaction,
 * depending on the card type and the configuration settings stored at the store
 * and/or the product variations of the transaction's order.
 */
interface DataInterface {

  /**
   * Prepares and returns the enhanced data for the given order and card type.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $order
   *   The order for which to get the data.
   * @param string $card_type
   *   The card type; different card types require different data.
   *
   * @return array
   *   Level 2/3 data.
   */
  public function getData(OrderInterface $order, $card_type);

}
