<?php

namespace Drupal\Tests\commerce_bluesnap\Unit;

use Drupal\commerce_bluesnap\FraudPrevention\FraudSession;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\PrivateTempStore;

use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\commerce_bluesnap\FraudPrevention\FraudSession
 * @group commerce_bluesnap
 */
class FraudSessionTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->fraud_session = new FraudSession(
      $this->mockPrivateTempStoreFactory()
    );
  }

  /**
   * Tests the iframe method.
   *
   * ::covers iframe.
   */
  public function testIframe() {
    // Expected output for iframe.
    $iframe = '
      <iframe
        width="1"
        height="1"
        frameborder="0"
        scrolling="no"
        src="{{ url }}/servlet/logo.htm?{{ params }}">
        <img width="1" height="1" src="{{ url }}/servlet/logo.gif?{{ params }}">
      </iframe>
    ';
    $expected_output = [
      '#type' => 'inline_template',
      '#template' => $iframe,
      '#context' => [
        'url' => 'https://sandbox.bluesnap.com',
        'params' => 's=12345',
      ],
    ];

    // Actual output.
    $actual_output = $this->fraud_session->iframe('sandbox');

    $this->assertEquals($expected_output, $actual_output);
  }

  /**
   * Mocks a private temp store factory.
   */
  protected function mockPrivateTempStoreFactory() {
    $temp_store_factory = $this->prophesize(PrivateTempStoreFactory::class);

    $private_temp_store = $this->mockPrivateTempStore();
    $temp_store_factory->get('commerce_bluesnap')
      ->willReturn($private_temp_store);

    return $temp_store_factory->reveal();
  }

  /**
   * Mocks a private tempstore.
   */
  protected function mockPrivateTempStore() {
    $private_temp_store = $this->prophesize(PrivateTempStore::class);

    $private_temp_store->get('fraud_session_id')->willReturn('12345');

    return $private_temp_store->reveal();
  }

}
