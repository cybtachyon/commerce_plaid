<?php

namespace Drupal\commerce_plaid\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;

/**
 * Provides the interface for the Plaid payment gateway.
 */
interface PlaidInterface extends OffsitePaymentGatewayInterface {

  /**
   * Creates Plaid Link token for payment_initiation.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The commerce payment.
   *
   * @return string
   *   The Link token.
   */
  public function createLinkToken(PaymentInterface $payment): string;

}
