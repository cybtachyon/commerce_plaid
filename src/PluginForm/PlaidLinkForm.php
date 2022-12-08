<?php

namespace Drupal\commerce_plaid\PluginForm;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides an offsite payment form for Plaid link.
 */
class PlaidLinkForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    try {
      $link_token = $this->plugin->createLinkToken($payment);
    }
    catch (\Exception $e) {
      watchdog_exception('commerce_plaid', $e);
      $response = (method_exists($e, 'getResponse') && !empty($e->getResponse())) ? print_r($e->getResponse(), TRUE) : '';
      $error_message = $this->t('Requesting of the Plaid Link token failed. Response: <pre>@response</pre>', ['@response' => $response]);
      throw new PaymentGatewayException($error_message);
    }

    $data['linkToken'] = $link_token;
    $data['returnUrl'] = $form['#return_url'];
    $data['cancelUrl'] = $form['#cancel_url'];
    $data['oauthPage'] = Url::fromRoute('commerce_plaid.oauth')->toString();
    $form['#attached']['library'][] = 'commerce_plaid/plaid_link';
    $form['#attached']['drupalSettings']['commercePlaid'] = $data;

    return $form;
  }

}
