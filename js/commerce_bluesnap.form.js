/**
 * @file
 * Javascript to generate BlueSnap token in PCI-compliant way.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Attaches the commerceBluesnapForm behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the commerceBluesnapForm behavior.
   * @prop {Drupal~behaviorDetach} detach
   *   Detaches the commerceBluesnapForm behavior.
   *
   * @see Drupal.commerceBluesnap
   */
  Drupal.behaviors.commerceBluesnapForm = {
    attach: function (context) {
      $('.bluesnap-form', context).once('bluesnap-processed').each(function () {
        var jsonData = {
          token: "HOSTEDFIELDTOKENID",
          title: "BlueSnap Example",
          description: "This is description for example...",
          img: "/developers/571747/download.jpg",
          amount: "150",
          currency: "EUR",
          buttonLabel: "Click to buy",
          billingDetails: true,
          includeEmail: true,
          language: "EN",
          shopperData: {
            firstname: "Someone",
            lastname: "JustExample"
          }
        }

        bluesnap.openCheckout(jsonData, function (eCheckoutResult) {
          // On success.
          if (eCheckoutResult.code == 1) {

          }
          // On error.
          else {
            console.log(eCheckoutResult.data);
          }
          bluesnap.closeCheckout();
        });
      });
    }
  }
})(jQuery, Drupal, drupalSettings);
