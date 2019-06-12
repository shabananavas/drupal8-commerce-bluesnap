<?php

namespace Drupal\commerce_bluesnap\Tests\Mocks;

/**
 * Class for mocking adjustments.
 *
 * The Adjustment class is marked as final and cannot be mocked using
 * reflection.
 */
class MockAdjustment {

  /**
   * Mock method for getting the type of the adjustment.
   *
   * @see \Drupal\commerce_order\Adjustment::getType()
   */
  public function getType() {}

  /**
   * Mock method for getting the amount of the adjustment.
   *
   * @see \Drupal\commerce_order\Adjustment::getAmount()
   */
  public function getAmount() {}

  /**
   * Mock method for getting the percentage of the adjustment.
   *
   * @see \Drupal\commerce_order\Adjustment::getPercentage()
   */
  public function getPercentage() {}

  /**
   * Mock method for getting the percentage of the adjustment.
   *
   * @see \Drupal\commerce_order\Adjustment::isIncluded()
   */
  public function isIncluded() {}

}
