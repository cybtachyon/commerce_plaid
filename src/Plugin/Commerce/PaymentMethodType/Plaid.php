<?php

namespace Drupal\commerce_plaid\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\CreditCard;

/**
 * Provides the Plaid payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "plaid",
 *   label = @Translation("Plaid"),
 *   create_label = @Translation("Plaid"),
 * )
 */
class Plaid extends CreditCard {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    return $this->t('Plaid');
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    return [];
  }

}
