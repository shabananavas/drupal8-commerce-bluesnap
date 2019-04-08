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

      });
    }
  }
})(jQuery, Drupal, drupalSettings);
