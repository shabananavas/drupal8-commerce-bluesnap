<?php

namespace Drupal\commerce_bluesnap\Tests\Mocks;

/**
 * Class for mocking adjustments.
 *
 * The Adjustment class is marked as final and cannot be mocked using
 * reflection.
 */
class MockCalculator {

  /**
   * Mock method for getting the type of the adjustment.
   *
   * @see \Drupal\commerce_price\Calculator::subtract()
   */
  public function subtract() {}

}
