<?php

namespace Drupal\commerce_bluesnap\PluginForm\Bluesnap;

use Drupal\commerce_payment\PluginForm\PaymentRefundForm as BasePaymentRefundForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Extends the base Commerce payment refund form.
 */
class PaymentRefundForm extends BasePaymentRefundForm {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // As we are letting the incoming BlueSnap IPN perform the actual updating
    // of this payment, the changes might not be immediately reflected in the
    // UI. So, inform the user of the same so that they are not confused.
    // @see the refundPayment() function in the OnsiteBase for more info.
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['#success_message'] = t(
      'The payment refund has been initiated on BlueSnap.
        The changes will be reflected as soon as the refund is confirmed on BlueSnap.'
    );

    return $form;
  }
}
