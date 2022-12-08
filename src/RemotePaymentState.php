<?php

namespace Drupal\commerce_plaid;

/**
 * Contains remote payment states.
 */
class RemotePaymentState {

  /**
   * This is the initial state of all payments.
   *
   * It indicates that the payment is waiting on user input to continue processing.
   *
   * A payment may re-enter this state later on if further input is needed.
   */
  const INPUT_NEEDED = 'PAYMENT_STATUS_INPUT_NEEDED';

  /**
   * The payment has been successfully authorised and accepted by the financial institution but has not been executed.
   */
  const INITIATED = 'PAYMENT_STATUS_INITIATED';

  /**
   * The payment has failed due to insufficient funds.
   */
  const INSUFFICIENT_FUNDS = 'The payment has failed due to insufficient funds.';

  /**
   * The payment has failed to be initiated.
   *
   * This error is retryable once the root cause is resolved.
   */
  const FAILED = 'PAYMENT_STATUS_FAILED';

  /**
   * The payment has been blocked. This is a retryable error.
   */
  const BLOCKED = 'PAYMENT_STATUS_BLOCKED';

  /**
   * The payment is currently being processed.
   *
   * The payment will automatically exit this state when the financial institution has authorised the transaction.
   */
  const AUTHORISING = 'PAYMENT_STATUS_AUTHORISING';

  /**
   * The payment was cancelled during authorisation.
   */
  const CANCELLED = 'PAYMENT_STATUS_CANCELLED';

  /**
   * The payment has been successfully executed and is considered complete.
   */
  const EXECUTED = 'PAYMENT_STATUS_EXECUTED';

  /**
   * The payment has settled and funds are available for use.
   *
   * Payment settlement can only be guaranteed by using Plaid virtual accounts.
   *
   * A payment will typically settle within seconds to several days, depending on which payment rail is used.
   */
  const SETTLED = 'PAYMENT_STATUS_SETTLED';

  /**
   * Indicates that the standing order has been successfully established.
   *
   * This state is only used for standing orders.
   */
  const ESTABLISHED = 'PAYMENT_STATUS_ESTABLISHED';

  /**
   * The payment was rejected by the financial institution.
   */
  const REJECTED = 'PAYMENT_STATUS_REJECTED';

}
