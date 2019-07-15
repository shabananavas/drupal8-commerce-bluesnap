<?php

namespace Drupal\Tests\commerce_bluesnap\Unit;

use Drupal\commerce_bluesnap\Api\ClientFactory;
use Drupal\commerce_bluesnap\Api\AltTransactionsClient;
use Drupal\commerce_bluesnap\Api\TransactionsClient;
use Drupal\commerce_bluesnap\Api\VaultedShoppersClient;
use Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields;
use Drupal\commerce_bluesnap\Ipn\Handler;
use Drupal\commerce_bluesnap\EnhancedData\Data;
use Drupal\commerce_bluesnap\FraudPrevention\FraudSessionInterface;

use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItemInterface;

use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Rounder;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethod;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Entity\EntityTypeManager;
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
use Drupal\state_machine\Plugin\Field\FieldType\StateItem;
use Drupal\user\Entity\User;

/**
 * @coversDefaultClass \Drupal\commerce_bluesnap\Plugin\Commerce\PaymentGateway\HostedPaymentFields
 * @group commerce_bluesnap
 */
class HostedPaymentFieldsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Construct a ECP payment gateway object.
    $this->hosted_payment_fields = new HostedPaymentFields(
      $this->pluginConfiguration(),
      'bluesnap_hosted_payment_fields',
      $this->pluginDefinition(),
      $this->mockEntityTypeManager(),
      $this->mockPaymentTypeManager(),
      $this->mockPaymentMethodTypeManager(),
      $this->mockTime(),
      $this->mockModuleHandler(),
      $this->mockRounder(),
      $this->mockClientFactory(),
      $this->mockHandler(),
      $this->mockEnhancedData(),
      $this->mockFraudSession()
    );
  }

  /**
   * Tests the prepareTransactionData method.
   *
   * ::covers prepareTransactionData.
   */
  public function testCreatePayment() {
    $payment = $this->mockPayment();
    $this->hosted_payment_fields->createPayment($payment);
    $expected_remote_id = '2345';

    $this->assertEquals($expected_remote_id, $payment->getRemoteId());
  }

  /**
   * Mocks an order.
   */
  protected function mockOrder($order_items) {
    $order = $this->prophesize(Order::class);

    $order->get('payment_method')->willReturn($this->mockPaymentMethod());
    $order->get('shipments')->willReturn($this->mockShipment());
    $order->get('billing_profile')->willReturn($this->mockBillingProfile());
    $order->getStoreId()->willReturn('1');

    $order->getEmail()->willReturn('wq9n918s3gq187k@marketplace.amazon.com');
    $order->getItems()->willReturn($order_items);
    $order->hasField('shipments')->willReturn(TRUE);
    $order->getPlacedTime()->willReturn('1529255747');
    $order->getOrderNumber()->willReturn('W0133934');

    return $order->reveal();
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

    $address_item->getGivenName()->willReturn('Seller');
    $address_item->getFamilyName()->willReturn('Central');
    $address_item->getAddressLine1()->willReturn('8 Fort Path Road');
    $address_item->getAddressLine2()->willReturn('');
    $address_item->getLocality()->willReturn('Madison');
    $address_item->getAdministrativeArea()->willReturn('CT');
    $address_item->getCountryCode()->willReturn('US');
    $address_item->getPostalCode()->willReturn('33634-6308');

    $profile_details->isEmpty()->willReturn(FALSE);
    $profile_details->first()->willReturn($address_item->reveal());

    return $profile_details->reveal();
  }

  /**
   * Mocks a payment method.
   */
  protected function mockPaymentMethod() {
    $payment_method = $this->prophesize(PaymentMethod::class);

    $card_number = $this->prophesize(StringData::class);
    $card_number->getString()->willReturn('4111111111111111');
    $payment_method->get('card_number')->willReturn($card_number->reveal());

    $card_type = $this->prophesize(StringData::class);
    $card_type->getString()->willReturn('mastercard');
    $payment_method->get('card_type')->willReturn($card_type->reveal());

    $payment_method->getRemoteId()->willReturn('675456');

    $payment_method->isExpired()->willReturn(FALSE);

    $payment_method->getOwner()->willReturn($this->mockUser());

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
    $payment_method->getBillingProfile()->willReturn($billing_profile->reveal());

    //$payment_method->set('routing_number', '75150')->willReturn($account_number->reveal());
    //$payment_method->set('account_number', '99992')->willReturn($account_number->reveal());
    //$payment_method->set('account_type', 'CONSUMER_CHECKING')->willReturn($account_number->reveal());

    $payment_method->setRemoteId('675456')->willReturn($payment_method->reveal());
    $payment_method->save()->willReturn($payment_method->reveal());

    return $payment_method->reveal();
  }

  /**
   * Mocks a payment.
   */
  protected function mockPayment() {
    $payment = $this->prophesize(Payment::class);
    $payment_method = $this->mockPaymentMethod();

    $order_items[] = $this->mockOrderItems();
    $order = $this->mockOrder($order_items);

    $payment->getAmount()->willReturn(new Price('19.99', 'USD'));
    $payment->getOrderId()->willReturn('W0133934');
    $payment->getOrder()->willReturn($order);
    $payment->getState()->willReturn($this->mockState());

    $payment->getPaymentMethod()->willReturn($payment_method);
    $payment->setState("completed")->willReturn($payment->reveal());
    $payment->setRemoteId('2345')->willReturn($payment->reveal());
    $payment->getRemoteId()->willReturn('2345');
    $payment->save()->willReturn($payment->reveal());

    return $payment->reveal();
  }

  /**
   * Mocks entity type manager.
   */
  protected function mockEntityTypeManager() {
    return $this->prophesize(EntityTypeManager::class)->reveal();
  }

  /**
   * Mocks Payment type manager.
   */
  protected function mockPaymentTypeManager() {
    return $this->prophesize(PaymentTypeManager::class)->reveal();
  }

  /**
   * Mocks payment method type manager.
   */
  protected function mockPaymentMethodTypeManager() {
    $payment_method_type_manager = $this->prophesize(PaymentMethodTypeManager::class);

    return $payment_method_type_manager->reveal();
  }

  /**
   * Mocks time.
   */
  protected function mockTime() {
    return $this->prophesize(Time::class)->reveal();
  }

  /**
   * Mocks module handler.
   */
  protected function mockModuleHandler() {
    return $this->prophesize(ModuleHandler::class)->reveal();
  }

  /**
   * Mocks rounder.
   */
  protected function mockRounder() {
    $rounder = $this->prophesize(Rounder::class);
    $rounder->round(new Price('19.99', 'USD'))->willReturn(new Price('20', 'USD'));

    return $rounder->reveal();
  }

  /**
   * Mocks state machine.
   */
  protected function mockState() {
    $state_item = $this->prophesize(StateItem::class);
    $state_item->getId()->willReturn('new');

    return $state_item->reveal();
  }

  /**
   * Mocks client factory.
   */
  protected function mockClientFactory() {
    $client_factory = $this->prophesize(clientFactory::class);
    $config = [
      "env" => "sandbox",
      "username" => "",
      "password" => ""
    ];
    $client_factory->get('recurring/ondemand', $config)
      ->willReturn($this->mockTransactionsClient());

    $client_factory->get('vaulted-shoppers', $config)
      ->willReturn($this->mockVaultedShoppersClient());

    $client_factory->get('transactions', $config)
      ->willReturn($this->mockTransactionsClient());

    return $client_factory->reveal();
  }

  /**
   * Mocks ALT transactions client.
   */
  protected function mockTransactionsClient() {
    $transactions_client = $this->prophesize(TransactionsClient::class);

    $result = (object)['id' => '2345'];
    $transactions_client->create($this->getTransactionData())->willReturn($result);

    return $transactions_client->reveal();
  }

  /**
   * Mocks ALT transactions client.
   */
  protected function mockVaultedShoppersClient() {
    $vaulted_shopper_client = $this->prophesize(VaultedShoppersClient::class);

    $result = (object)['id' => '675456'];
    $vaulted_shopper_client->create($this->getVaultedShopperTransactionData())
      ->willReturn($result);

    return $vaulted_shopper_client->reveal();
  }

  /**
   * Mocks user.
   */
  protected function mockUser() {
    $user = $this->prophesize(user::class);
    $user->isAuthenticated()->willReturn(FALSE);

    return $user->reveal();
  }

  /**
   * Mocks IPN Handler.
   */
  protected function mockHandler() {
    return $this->prophesize(Handler::class)->reveal();
  }

  /**
   * Mocks Enhanced data.
   */
  protected function mockEnhancedData() {
    $enhanced_data = $this->prophesize(Data::class);

    $payment = $this->mockPayment();
    $payment_method = $this->mockPaymentMethod();

    /*$enhanced_data->getData(
      $payment->getOrder(),
      'mastercard'
    )->willReturn([]);*/

    return $enhanced_data->reveal();
  }

  /**
   * Mocks Fraud session.
   */
  protected function mockFraudSession() {
    return $this->prophesize(FraudSessionInterface::class)->reveal();
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

    return $order_item->reveal();
  }

  /**
   * Returns configuration array containing information about the HPP instance.
   */
  protected function pluginConfiguration() {
    return ['payment_method_types' => ['bluesnap_hosted_payment_fields']];
  }

  /**
   * Returns HPP plugin implementation definition.
   */
  protected function pluginDefinition() {
    return [
      'payment_type' => 'bluesnap_hosted_payment_fields',
      'id' => 'bluesnap_hosted_payment_fields',
      'payment_method_types' => ['bluesnap_hosted_payment_fields'],
      'label' => 'BlueSnap (Hosted Payment Fields)',
      'display_label' => 'BlueSnap (Hosted Payment Fields)',
      'forms' => [
        'add-payment-method' => "Drupal\commerce_bluesnap\PluginForm\Bluesnap\HostedPaymentFieldsPaymentMethodAddForm",
      ],
      'modes' => ['online'],
    ];
  }

  /**
   * Returns sample alt transaction data.
   */
  protected function getTransactionData() {
    return  [
      "currency" => "USD",
      "amount" => "20",
      "cardTransactionType" => "AUTH_CAPTURE",
      "transactionFraudInfo" => [
        "fraudSessionId" => null
      ],
      "transactionMetadata" => [
        "metaData" => [
          [
            "metaKey" => "order_id",
            "metaValue" => "W0133934",
            "metaDescription" => "The transaction's order ID."
          ],
          [
            "metaKey" => "store_id",
            "metaValue" => "1",
            "metaDescription" => "The transaction's store ID."
          ]
        ]
      ],
      "creditCard" => [
        "cardLastFourDigits" => "4111111111111111",
        "cardType" => "mastercard"
      ],
      "vaultedShopperId" => "675456"
    ];
  }

  /**
   * Returns sample vaulted shopper transaction data.
   */
  protected function getVaultedShopperTransactionData() {
    return  [
      "firstName" => "Seller",
      "lastName" => "Central",
      "address2" => "",
      "city" => "Madison",
      "state" => "CT",
      "zip" => "33634-6308",
      "country" => "US",
      "address" => "8 Fort Path Road",
      "paymentSources" => [
        "ecpDetails" => [
          [
            "ecp" => [
              "routingNumber" => "011075150",
              "accountType" => "CONSUMER_CHECKING",
              "accountNumber" => "4099999992"
            ]
          ]
        ]
      ]
    ];
  }

  /**
   * Returns sample payment details data.
   */
  protected function getPaymentDetails() {
    return [
      'routing_number' => '011075150',
      'account_type' => 'CONSUMER_CHECKING',
      'account_number' => '4099999992',
    ];
  }

  /**
   * Returns sample subscription transaction data.
   */
  protected function getSubscriptionTransactionData() {
    return [
      'currency' => 'USD',
      'amount' => '20',
      // Authorization is captured by the payment method form.
      'authorizedByShopper' => 1,
      'transactionMetadata' => [
        'metaData' => [
          [
            'metaKey' => 'order_id',
            'metaValue' => 'W0133934',
            'metaDescription' => 'The transaction\'s order ID.',
          ],
          [
            'metaKey' => 'store_id',
            'metaValue' => '1',
            'metaDescription' => 'The transaction\'s store ID.',
          ],
        ],
      ],
      // Note that the account/routing numbers must already be truncated.
      'ecpTransaction' => [
        'publicAccountNumber' => '4099999992',
        'publicRoutingNumber' => '011075150',
        'accountType' => 'CONSUMER_CHECKING',
      ],
      'vaultedShopperId' => '675456',
      'paymentSource' => [
        'ecpInfo' => [
          'billingContactInfo' => [
            'firstName' => 'Seller',
            'lastName' => 'Central',
            'address1' => '8 Fort Path Road',
            'address2' => '',
            'city' => 'Madison',
            'state' => 'CT',
            'zip' => '33634-6308',
            'country' => 'US',
          ],
          'ecp' => [
            'routingNumber' => '011075150',
            'accountType' => 'CONSUMER_CHECKING',
            'accountNumber' => '4099999992',
          ],
        ]
      ]
    ];
  }
}
