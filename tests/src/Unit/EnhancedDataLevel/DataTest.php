<?php

namespace Drupal\Tests\commerce_bluesnap\Unit;

use Drupal\commerce_bluesnap\EnhancedDataLevel\Data;
use Drupal\commerce_bluesnap\Tests\Mocks\MockAdjustment;

use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItemInterface;

use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethod;

use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\TypedData\Plugin\DataType\StringData;

use Drupal\physical\Plugin\Field\FieldType\MeasurementItem;
use Drupal\physical\Weight;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\commerce_bluesnap\EnhancedDataLevel\Data
 * @group commerce_bluesnap
 */
class DataTest extends UnitTestCase {

  /**
   * The availability manager.
   *
   * @var \Drupal\commerce_order\Adjustment
   */
  protected $adjustment;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->data = $this->getMockBuilder(Data::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Use reflection to make level2Data() public.
    $ref_level_2_data = new \ReflectionMethod($this->data, 'level2Data');
    $ref_level_2_data->setAccessible(TRUE);
    $this->level_2_data = $ref_level_2_data;

    // Use reflection to make level3Data() public.
    $ref_level_3_data = new \ReflectionMethod($this->data, 'level3Data');
    $ref_level_3_data->setAccessible(TRUE);
    $this->level_3_data = $ref_level_3_data;

    // Use reflection to make level3DataItems() public.
    $ref_level_3_data_items = new \ReflectionMethod($this->data, 'level3DataItems');
    $ref_level_3_data_items->setAccessible(TRUE);
    $this->level_3_data_items = $ref_level_3_data_items;

    // Mock order object.
    $order_items[] = $this->mockOrderItems();
    $this->order = $this->mockOrder($order_items);
  }

  /**
   * Tests the level2Data function for a Master card.
   *
   * ::covers level2Data.
   */
  public function testLevel2DataMasterCard() {
    // Expected output for mastercard.
    $expected_output = ['customerReferenceNumber' => 'W0133934'];

    // Actual output.
    $actual_output = $this->level_2_data
      ->invokeArgs($this->data, [$this->order, 'mastercard']);

    $this->assertEquals($expected_output, $actual_output);
  }

  /**
   * Tests the level2Data function for a Amex card.
   *
   * ::covers level2Data.
   */
  public function testLevel2DataAmex() {
    // Expected output for amex card.
    $expected_output = [
      'customerReferenceNumber' => 'W0133934',
      'destinationZipCode' => '33634-6308',
      'level3DataItem' => [
        '0' => [
          'lineItemTotal' => '101.9',
          'description' => 'T-shirt (red, small)',
          'itemQuantity' => '2.00',
        ],
      ],
    ];

    // Actual output for amex card.
    $actual_output = $this->level_2_data
      ->invokeArgs($this->data, [$this->order, 'amex']);

    // Call level2Data() on the data class.
    $this->assertEquals($expected_output, $actual_output);
  }

  /**
   * Tests the level3DataItems function for a master card.
   *
   * ::covers level3DataItems.
   */
  public function testlevel3DataItemsMaster() {
    // Expected output for master card.
    $expected_output = [
      '0' => [
        'lineItemTotal' => '101.9',
        'description' => 'T-shirt (red, small)',
        'itemQuantity' => '2.00',
        'unitCost' => '50.950000',
        'productCode' => '2728',
        'discountAmount' => '10',
        'discountIndicator' => 'N',
        'taxAmount' => '6.1',
        'grossNetIndicator' => 'N',
        'taxRate' => '0.06',
      ],
    ];

    // Actual output for master card.
    $actual_output = $this->level_3_data_items
      ->invokeArgs($this->data, [$this->order, 'mastercard']);

    // Call level3DataItems() on the data class.
    $this->assertEquals($expected_output, $actual_output);
  }

  /**
   * Tests the level3Data function for a master card.
   *
   * ::covers level3Data.
   */
  public function testlevel3DataMaster() {
    // Expected output for master card.
    // We have not added order level discount or tax,
    // hence those info should not be there in the output.
    $expected_output = [
      'freightAmount' => '10',
      'customerReferenceNumber' => 'W0133934',
      'destinationZipCode' => '33634-6308',
      'destinationCountryCode' => 'us',
      'level3DataItems' => [
        '0' => [
          'lineItemTotal' => '101.9',
          'description' => 'T-shirt (red, small)',
          'itemQuantity' => '2.00',
          'unitCost' => '50.950000',
          'productCode' => '2728',
          'discountAmount' => '10',
          'discountIndicator' => 'N',
          'taxAmount' => '6.1',
          'grossNetIndicator' => 'N',
          'taxRate' => '0.06',
        ],
      ]
    ];

    // Actual output for master card.
    $actual_output = $this->level_3_data
      ->invokeArgs($this->data, [$this->order, 'mastercard']);

    // Call level3DataItems() on the data class.
    $this->assertEquals($expected_output, $actual_output);
  }

  /**
   * Mocks an order.
   */
  protected function mockOrder($order_items) {
    $order = $this->prophesize(Order::class);

    $order->get('payment_method')->willReturn($this->mockPaymentMethod());
    $order->get('shipments')->willReturn($this->mockShipment());
    $order->get('billing_profile')->willReturn($this->mockBillingProfile());

    $order->getEmail()->willReturn('wq9n918s3gq187k@marketplace.amazon.com');
    $order->getItems()->willReturn($order_items);
    $order->hasField('shipments')->willReturn(TRUE);
    $order->getPlacedTime()->willReturn('1529255747');
    $order->getOrderNumber()->willReturn('W0133934');

    // Adjustments.
    $adjustments['shipping'] = [
      'type' => 'promotion',
      'amount' => new Price('10', 'USD'),
      'isIncluded' => FALSE,
    ];

    $order->collectAdjustments(["shipping"])->willReturn($this->mockAdjustment($adjustments['shipping']));
    $order->collectAdjustments(["tax"])->willReturn([]);
    $order->collectAdjustments(["promotion"])->willReturn([]);

    return $order->reveal();
  }

  /**
   * Returns mock adjustments for the given data.
   *
   * @param array $adjustments
   *   The adjustment' data.
   *
   * @return \Drupal\commerce_bluesnap\Tests\Mocks\MockAdjustment[]
   *   The adjustment mock objects.
   */
  protected function mockAdjustments(array $adjustments) {
    $mock_adjustments = [];

    foreach ($adjustments as $adjustment) {
      $mock_adjustment = $this->prophesize(MockAdjustment::class);
      $mock_adjustment->getAmount()->willReturn($adjustment['amount']);
      if (!empty($adjustment['percentage'])) {
        $mock_adjustment->getPercentage()->willReturn($adjustment['percentage']);
      }
      $mock_adjustment->isIncluded()->willReturn($adjustment['isIncluded']);

      $mock_adjustments[] = $mock_adjustment->reveal();
    }

    return $mock_adjustments;
  }

  /**
   * Returns mock adjustment for the given data.
   *
   * @param array $adjustment
   *   The adjustment' data.
   *
   * @return \Drupal\commerce_bluesnap\Tests\Mocks\MockAdjustment
   *   The adjustment mock object.
   */
  protected function mockAdjustment(array $adjustment) {
    $mock_adjustment = $this->prophesize(MockAdjustment::class);
    $mock_adjustment->getAmount()->willReturn($adjustment['amount']);
    if (!empty($adjustment['percentage'])) {
      $mock_adjustment->getPercentage()->willReturn($adjustment['percentage']);
    }
    $mock_adjustment->isIncluded()->willReturn($adjustment['isIncluded']);

    $mock_adjustments[] = $mock_adjustment->reveal();

    return $mock_adjustments;
  }

  /**
   * Mocks a billing profile.
   */
  protected function mockBillingProfile() {
    $billing_profile = $this->prophesize(Profile::class);
    $billing_profile->get('address')->willReturn($this->mockProfileDetails([
      'given_name' => 'Seller',
      'additional_name' => '',
      'family_name' => 'Central',
      'organization' => 'Amazon.com',
      'address_line1' => '98 Fort Path Road',
      'address_line2' => '',
      'locality' => 'Madison',
      'administrative_area' => 'CT',
      'postal_code' => '06443',
      'country' => 'US',
    ]));

    $billing_field_ref = $this->prophesize(EntityReference::class);
    $billing_field_ref->getValue()->willReturn($billing_profile->reveal());
    $billing_field_ref_item = $this->prophesize(EntityReferenceItem::class);
    $billing_field_ref_item->get('entity')->willReturn($billing_field_ref->reveal());
    $billing_field = $this->prophesize(EntityReferenceFieldItemList::class);
    $billing_field->first()->willReturn($billing_field_ref_item->reveal());

    $phone = $this->prophesize(StringData::class);
    $phone->getString()->willReturn('');
    $phone_field = $this->prophesize(FieldItemList::class);
    $phone_field->isEmpty()->willReturn(FALSE);
    $phone_field->first()->willReturn($phone->reveal());
    $billing_profile->get('field_phone')->willReturn($phone_field->reveal());

    $billing_field->isEmpty()->willReturn(FALSE);
    $billing_field->referencedEntities()->willReturn([$billing_profile->reveal()]);

    return $billing_field->reveal();
  }

  /**
   * Mocks a shipment.
   */
  protected function mockShipment() {
    $remote_id = $this->prophesize(StringItem::class);
    $remote_id->getString()->willReturn('02');

    $remote_id_field = $this->prophesize(FieldItemList::class);
    $remote_id_field->isEmpty()->willReturn(FALSE);
    $remote_id_field->first()->willReturn($remote_id->reveal());

    $shipping_method = $this->prophesize(ShippingMethod::class);
    $shipping_method->get('remote_id')->willReturn($remote_id_field->reveal());

    $shipping_profile = $this->prophesize(Profile::class);
    $shipping_profile->get('address')->willReturn($this->mockProfileDetails([
      'given_name' => 'Tampa Bay',
      'additional_name' => '',
      'family_name' => 'Digital',
      'organization' => '',
      'address_line1' => '4710 EISENHOWER BLVD STE B12',
      'address_line2' => '',
      'locality' => 'TAMPA',
      'administrative_area' => 'FL',
      'postal_code' => '33634-6308',
      'country' => 'US',
    ]
    ));

    $shipment = $this->prophesize(ShipmentInterface::class);
    $shipment->getShippingMethod()->willReturn($shipping_method->reveal());
    $shipment->getShippingProfile()->willReturn($shipping_profile->reveal());

    $shipments_field = $this->prophesize(EntityReferenceFieldItemList::class);
    $shipments_field->isEmpty()->willReturn(FALSE);
    $shipments_field->referencedEntities()->willReturn([$shipment->reveal()]);

    return $shipments_field->reveal();
  }

  /**
   * Mocks profile details.
   */
  protected function mockProfileDetails(array $details) {
    $profile_details = $this->prophesize(FieldItemList::class);
    $address_item = $this->prophesize(AddressItem::class);

    $address_item->getPostalCode()->willReturn('33634-6308');
    $address_item->getCountryCode()->willReturn('us');

    $profile_details->isEmpty()->willReturn(FALSE);
    $profile_details->first()->willReturn($address_item->reveal());

    return $profile_details->reveal();
  }

  /**
   * Mocks a payment method.
   */
  protected function mockPaymentMethod() {
    $payment_method = $this->prophesize(PaymentMethod::class);

    $number_field = $this->prophesize(FieldItemList::class);
    $number_field->isEmpty()->willReturn(FALSE);
    $number_string = $this->prophesize(StringItem::class);
    $number_string->getString()->willReturn('114-8760622-4326602');
    $number_field->first()->willReturn($number_string->reveal());

    $payment_method_ref = $this->prophesize(EntityReference::class);
    $payment_method_ref->getValue()->willReturn($payment_method->reveal());

    $payment_method_ref_item = $this->prophesize(EntityReferenceItem::class);
    $payment_method_ref_item->get('entity')
      ->willReturn($payment_method_ref->reveal());

    $payment_method_list = $this->prophesize(FieldItemList::class);
    $payment_method_list->first()
      ->willReturn($payment_method_ref_item->reveal());

    return $payment_method_list->reveal();
  }

  /**
   * Mocks order items.
   */
  protected function mockOrderItems() {
    $weight_item = $this->prophesize(MeasurementItem::class);
    $weight_item->toMeasurement()->willReturn(new Weight('10', 'kg'));

    $weight_list = $this->prophesize(FieldItemListInterface::class);
    $weight_list->isEmpty()->willReturn(FALSE);
    $weight_list->first()->willReturn($weight_item->reveal());

    $purchased_entity = $this->prophesize(ProductVariation::class);
    $purchased_entity->id()->willReturn(3001);
    $purchased_entity->getEntityTypeId()
      ->willReturn('commerce_product_variation');
    $purchased_entity->hasField('weight')->willReturn(TRUE);
    $purchased_entity->get('weight')->willReturn($weight_list->reveal());
    $purchased_entity->getSku()->willReturn('2728');
    $purchased_entity = $purchased_entity->reveal();

    $order_item = $this->prophesize(OrderItemInterface::class);
    $order_item->id()->willReturn(2002);
    $order_item->getTitle()->willReturn('T-shirt (red, small)');
    $order_item->getPurchasedEntity()->willReturn($purchased_entity);
    $order_item->getUnitPrice()->willReturn(new Price('50.950000', 'USD'));
    $order_item->getQuantity()->willReturn('2.00');
    $order_item->getTotalPrice()->willReturn(new Price('98', 'USD'));

    // Tax and promotion Adjustments.
    $adjustments['tax'] = [
      'type' => 'tax',
      'amount' => new Price('6.1', 'USD'),
      'percentage' => '0.06',
      'isIncluded' => TRUE,
    ];
    $adjustments['promotion'] = [
      'type' => 'promotion',
      'amount' => new Price('-10', 'USD'),
      'isIncluded' => TRUE,
    ];

    $order_item->getAdjustments(['tax', 'promotion'])->willReturn($this->mockAdjustments($adjustments));
    $order_item->getAdjustments(["tax"])->willReturn($this->mockAdjustment($adjustments['tax']));
    $order_item->getAdjustments(["promotion"])->willReturn($this->mockAdjustment($adjustments['promotion']));

    return $order_item->reveal();
  }

}
