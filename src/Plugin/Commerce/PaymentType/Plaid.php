<?php

namespace Drupal\commerce_plaid\Plugin\Commerce\PaymentType;

use Drupal\commerce_payment\Plugin\Commerce\PaymentType\PaymentTypeBase;

/**
 * Provides the payment type for PayPal Checkout.
 *
 * @CommercePaymentType(
 *   id = "plaid",
 *   label = @Translation("Plaid"),
 *   workflow = "payment_plaid",
 * )
 */
class Plaid extends PaymentTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    return [];
  }

}
