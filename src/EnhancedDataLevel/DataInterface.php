<?php

namespace Drupal\commerce_bluesnap\EnhancedDataLevel;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Interface to process enhanced data in blueSnap transaction.
 */
interface DataInterface {

  /**
   * Returns the level 2/3 data for the given order and card type.
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
