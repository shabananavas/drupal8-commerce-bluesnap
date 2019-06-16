<?php

namespace Drupal\commerce_bluesnap_currency\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    // Alter the form callback for CommerceCurrencyResolverConversion form.
    // Use the commerce_bluesnap_currency form so as to embedd bluesnap currency
    // resolver settings element.
    $route = $collection->get('commerce_currency_resolver.currency_conversion');
    $route
      ->setDefault(
        '_form',
        '\Drupal\commerce_bluesnap_currency\Form\CommerceBlueSnapCurrencyResolverConversion'
      );
  }

}
