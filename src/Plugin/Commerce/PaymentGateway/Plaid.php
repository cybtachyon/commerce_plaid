<?php

namespace Drupal\commerce_plaid\Plugin\Commerce\PaymentGateway;

use ChkltLabs\Plaid\Entities\BacsAccount;
use ChkltLabs\Plaid\Entities\RecipientAddress;
use ChkltLabs\Plaid\Entities\User as PlaidUser;
use ChkltLabs\Plaid\Plaid as PlaidClient;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_plaid\PluginForm\PlaidLinkForm;
use Drupal\commerce_plaid\RemotePaymentState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Plaid payment gateway.
 */
#[CommercePaymentGateway(
  id: "plaid",
  label: new TranslatableMarkup("Plaid"),
  display_label: new TranslatableMarkup("Plaid"),
  modes: [
    "development" => new TranslatableMarkup("Development"),
    "production" => new TranslatableMarkup("Production"),
    "sandbox" => new TranslatableMarkup("Sandbox"),
  ],
  forms: [
    "offsite-payment" => PlaidLinkForm::class,
  ],
  payment_type: "plaid",
  payment_method_types: ['plaid'],
  credit_card_types: ['amex', 'discover', 'mastercard', 'visa'],
  requires_billing_information: FALSE,
)]
class Plaid extends OffsitePaymentGatewayBase implements PlaidInterface {

  /**
   * All Payment Initiation webhooks have a webhook_type of PAYMENT_INITIATION.
   */
  const WEBHOOK_TYPE = 'PAYMENT_INITIATION';

  /**
   * Webhook code for updating payment status.
   */
  const WEBHOOK_CODE = 'PAYMENT_STATUS_UPDATE';

  // How long tokens could be stored for maximum amount of time.
  const JTW_EXPIRATION = 86400;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->api = new PlaidClient(
      $this->configuration['client_id'] ?? '',
      $this->configuration['secret_key'] ?? '',
        $this->configuration['mode'] ?? 'development'
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.channel.commerce_plaid');
    $instance->languageManager = $container->get('language_manager');
    $instance->keyValueExpirable = $container->get('keyvalue.expirable');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'client_id' => '',
      'client_name' => '',
      'country_codes' => '',
      'log_api_calls' => '',
      'recipient' => '',
      'secret_key' => '',
      'validate_key' => 1,
      'webhook_base_url' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['client_id'] = [
      '#default_value' => $this->configuration['client_id'],
      '#required' => TRUE,
      '#title' => $this->t('Client ID'),
      '#type' => 'textfield',
    ];
    $form['secret_key'] = [
      '#default_value' => $this->configuration['secret_key'],
      '#required' => TRUE,
      '#title' => $this->t('Secret key'),
      '#type' => 'textfield',
    ];
    $form['validate_key'] = [
      '#default_value' => $this->configuration['validate_key'],
      '#description' => $this->t('Validate key on saving the settings form by making request to Plaid API.'),
      '#title' => $this->t('Validate key'),
      '#type' => 'checkbox',
    ];
    $form['client_name'] = [
      '#default_value' => $this->configuration['client_name'],
      '#description' => $this->t('The name of your application, as it should be displayed in Link. Maximum length of 30 characters.<br>If a value longer than 30 characters is provided, Link will display "This Application" instead.'),
      '#required' => TRUE,
      '#title' => $this->t('Client name'),
      '#type' => 'textfield',
    ];
    $form['recipient'] = [
      '#title' => $this->t('Recipient'),
      '#type' => 'fieldset',
    ];
    $form['recipient']['bacs'] = [
      '#description' => $this->t("The account number and sort code of the recipient's account."),
      '#title' => $this->t('BACS'),
      '#type' => 'fieldset',
    ];
    $form['recipient']['iban'] = [
      '#default_value' => $this->configuration['recipient']['iban'] ?? NULL,
      '#description' => $this->t('The International Bank Account Number (IBAN) for the recipient.<br>If BACS data is not provided, an IBAN is required.'),
      '#maxlength' => '34',
      '#size' => 34,
      '#title' => $this->t('IBAN'),
      '#type' => 'textfield',
    ];
    $form['recipient']['bacs']['account_number'] = [
      '#default_value' => $this->configuration['recipient']['bacs']['account_number'] ?? NULL,
      '#maxlength' => '10',
      '#size' => 10,
      '#title' => $this->t('Account number'),
      '#type' => 'textfield',
    ];
    $form['recipient']['bacs']['sort_code'] = [
      '#default_value' => $this->configuration['recipient']['bacs']['sort_code'] ?? NULL,
      '#maxlength' => '6',
      '#size' => 6,
      '#title' => $this->t('Sort code'),
      '#type' => 'textfield',
    ];
    $supported_country_codes = $this->getSupportedCountryCodes();
    $form['country_codes'] = [
      '#default_value' => $this->configuration['country_codes'],
      '#description' => $this->t('Institutions from all listed countries will be shown in Plaid Link.<br> More info https://plaid.com/docs/api/tokens/#link-token-create-request-country-codes'),
      '#options' => array_combine($supported_country_codes, $supported_country_codes),
      '#required' => TRUE,
      '#title' => $this->t('Country codes'),
      '#type' => 'checkboxes',
    ];
    $form['webhook_base_url'] = [
      '#default_value' => $this->configuration['webhook_base_url'],
      '#description' => $this->t('Can be used custom webhook base URL for testing. For example if ngrok is used on local environment.<br> Leave empty if the base URL is the same as the base URL of the site.'),
      '#title' => $this->t('Webhook base URL'),
      '#type' => 'url',
    ];
    $form['log_api_calls'] = [
      '#default_value' => $this->configuration['log_api_calls'],
      '#description' => $this->t('Log API call.'),
      '#title' => $this->t('Log API calls.'),
      '#type' => 'checkbox',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    if (empty($values['recipient']['iban']) && empty($values['recipient']['bacs']['account_number'])) {
      $form_state->setError($form['recipient']['iban'], $this->t('An IBAN is required, if BACS data is not provided.'));
    }
    if (!empty($values['recipient']['iban'])) {
      $iban_length = strlen($values['recipient']['iban']);
      if ($iban_length < 15 || $iban_length > 34) {
        $form_state->setError($form['recipient']['iban'], $this->t('Check your IBAN number. Min length: 15. Max length: 34.'));
      }
    }
    if (!empty($values['recipient']['bacs']['account_number'])) {
      if (strlen($values['recipient']['bacs']['account_number']) > 10) {
        $form_state->setError($form['recipient']['bacs']['account_number'], $this->t('Check your account number. Max length: 10.'));
      }
      if (empty($values['recipient']['bacs']['sort_code'])) {
        $form_state->setError($form['recipient']['bacs']['sort_code'], $this->t('Sort code must be provided.'));
      }
    }
    if (!empty($values['recipient']['bacs']['sort_code'])) {
      if (strlen($values['recipient']['bacs']['sort_code']) != 6) {
        $form_state->setError($form['recipient']['bacs']['sort_code'], $this->t('Check your sort code. Must be 6 digits'));
      }
      if (empty($values['recipient']['bacs']['account_number'])) {
        $form_state->setError($form['recipient']['bacs']['account_number'], $this->t('Account number must be provided.'));
      }
    }

    if (empty($values['validate_key'])) {
      return;
    }

    // Test client_id and secret_key.
    try {
      $test_credentials_client = new PlaidClient(
        $values['client_id'],
        $values['secret_key'],
        $values['mode'],
      );
      $test_credentials_client->payments->listRecipients();
      $this->messenger()->addMessage($this->t('Connectivity to Plaid successfully verified.'));
    }
    catch (\Exception $exception) {
      $response = $exception->getResponse();
      $error_code = $response->error_code ?? '';
      $error_message = $response->error_message ?? '';
      $this->messenger()->addError($this->t('Invalid client_id or secret_key specified. Error code: "@error_code". Error message: "@error_message"', ['@error_code' => $error_code, '@error_message' => $error_message]));
      $form_state->setError($form['client_id']);
      $form_state->setError($form['secret_key']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['client_id'] = $values['client_id'];
      $this->configuration['secret_key'] = $values['secret_key'];
      $this->configuration['validate_key'] = $values['validate_key'];
      $this->configuration['client_name'] = $values['client_name'];
      $this->configuration['recipient'] = $values['recipient'] ?? NULL;
      $this->configuration['webhook_base_url'] = $values['webhook_base_url'];
      $this->configuration['log_api_calls'] = $values['log_api_calls'];
      foreach ($values['country_codes'] as $key => $value) {
        if (empty($value)) {
          unset($values['country_codes'][$key]);
        }
      }
      $this->configuration['country_codes'] = $values['country_codes'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createLinkToken(PaymentInterface $payment): string {
    $order = $payment->getOrder();
    /** @var \Drupal\commerce_store\Entity\StoreInterface $store */
    $store = $order->getStore();
    $name = $store->getName();

    $log_api_calls = $this->configuration['log_api_calls'] ?? FALSE;

    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $store_address */
    $store_address = $store->getAddress();
    $street = $store_address->getAddressLine1();
    $street2 = $store_address->getAddressLine2();
    $postal_code = $store_address->getPostalCode();
    $city = $store_address->getLocality();
    $country = $store_address->getCountryCode();
    $address = new RecipientAddress($street, $street2, $city, $postal_code, $country);

    $recipient_data = $this->configuration['recipient'];
    $account = NULL;
    if (!empty($recipient_data['bacs']['account_number']) && !empty($recipient_data['bacs']['sort_code'])) {
      $account = new BacsAccount($recipient_data['bacs']['account_number'], $recipient_data['bacs']['sort_code']);
    }
    elseif (!empty($recipient_data['iban'])) {
      $account = $recipient_data['iban'];
    }
    if ($log_api_calls) {
      $this->logRequestParams('Recipient create', [
        '@account' => print_r($account, TRUE),
        '@address' => print_r($address, TRUE),
        '@name' => $name,
      ]);
    }
    $recipient = $this->api->payments->createRecipient($name, $account, $address);
    if ($log_api_calls) {
      $this->logger->debug('Response of the Recipient create request: <pre>@response</pre>', [
        '@response' => print_r($recipient, TRUE),
      ]);
    }

    $amount = $payment->getAmount()->getNumber();
    $currency = $payment->getAmount()->getCurrencyCode();
    $reference = $order->id();
    if ($log_api_calls) {
      $this->logRequestParams('Payment create', [
        '@amount' => $amount,
        '@currency' => $currency,
        '@recipient_id' => $recipient->recipient_id,
        '@reference' => $reference,
      ]);
    }
    // Be sure that amount will be correctly json encoded in ChkltLabs\Plaid\Resources\AbstractResource::buildRequest(). See https://bugs.php.net/bug.php?id=74221.
    ini_set('serialize_precision', -1);
    $plaid_payment = $this->api->payments->create($recipient->recipient_id, $reference, $amount, $currency);
    if ($log_api_calls) {
      $this->logger->debug('Response of the Payment create request: <pre>@response</pre>', [
        '@response' => print_r($plaid_payment, TRUE),
      ]);
    }
    $payment->setRemoteId($plaid_payment->payment_id);
    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    $language = in_array($current_language, $this->getSupportedLanguages()) ? $current_language : 'en';
    $products = ['payment_initiation'];
    $redirect_uri = Url::fromRoute('commerce_plaid.oauth', [], ['absolute' => TRUE])->toString();
    $webhook = $this->getNotifyUrl()->toString();
    if ($this->configuration['webhook_base_url']) {
      $notify_relative_url = Url::fromRoute('commerce_payment.notify', [
        'commerce_payment_gateway' => $this->parentEntity->id(),
      ])->toString();
      $webhook = rtrim($this->configuration['webhook_base_url'], '/') . $notify_relative_url;
    }

    $user_id = new PlaidUser($order->getCustomerId());
    $country_codes = array_keys($this->configuration['country_codes']);
    $client_name = $this->configuration['client_name'];
    if ($log_api_calls) {
      $this->logRequestParams('Link token create', [
        '@client_name' => $client_name,
        '@country_codes' => print_r($country_codes, TRUE),
        '@language' => $language,
        '@payment_id' => $plaid_payment->payment_id,
        '@products' => print_r($products, TRUE),
        '@redirect_uri' => $redirect_uri,
        '@user_id' => print_r($user_id, TRUE),
        '@webhook' => $webhook,
      ]);
    }
    $link_token = $this->api->tokens->create($client_name, $language, $country_codes, $user_id, $products, $webhook, NULL, NULL, NULL, $redirect_uri, NULL, $plaid_payment->payment_id);
    return $link_token->link_token;
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    if (!$this->isVerifiedWebhookRequest($request)) {
      return;
    }

    parent::onNotify($request);
    $content = $request->getContent();
    $content = json_decode($content, TRUE);
    if ($this->configuration['log_api_calls']) {
      $this->logger->debug('Plaid webhook request: <pre>@content</pre>', [
        '@content' => print_r($content, TRUE),
      ]);
    }
    if (empty($content['webhook_type']) || empty($content['webhook_code'])) {
      return;
    }
    if ($content['webhook_type'] !== self::WEBHOOK_TYPE || $content['webhook_code'] !== self::WEBHOOK_CODE) {
      return;
    }
    if (empty($content['payment_id'])) {
      return;
    }
    if (empty($content['new_payment_status'])) {
      return;
    }
    /** @var \Drupal\commerce_payment\PaymentStorageInterface $commerce_payment_storage */
    $commerce_payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    /** @var \Drupal\commerce_order\OrderStorage $commerce_order_storage */
    $commerce_order_storage = $this->entityTypeManager->getStorage('commerce_order');

    $payment = $commerce_payment_storage->loadByRemoteId($content['payment_id']);
    if (!$payment && !empty($content['original_reference'])) {
      $order = $commerce_order_storage->load($content['original_reference']);
      if ($order) {
        $payment = $commerce_payment_storage->create([
          'amount' => $order->getBalance(),
          'order_id' => $order->id(),
          'payment_gateway' => $this->parentEntity->id(),
          'remote_id' => $content['payment_id'],
          'remote_state' => $content['new_payment_status'],
          'state' => 'new',
        ]);
      }
    }
    else {
      $payment->setRemoteState($content['new_payment_status']);
      $this->logger->notice('Remote status of the Payment @payment_id was updated to ', [
        '@new_status' => $content['new_payment_status'],
        '@payment_id' => $payment->id(),
      ]);
    }
    switch ($content['new_payment_status']) {
      case RemotePaymentState::AUTHORISING:
        $payment->setState('processing');
        break;

      case RemotePaymentState::REJECTED:
      case RemotePaymentState::CANCELLED:
      case RemotePaymentState::INSUFFICIENT_FUNDS:
        $payment->setState('voided');
        break;

      case RemotePaymentState::EXECUTED:
        $payment->setState('completed');
        break;
    }
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request): void {
    if (!empty($request->query->get('error_code'))) {
      $message = $this->getPaymentErrorMessage($request->query->get('error_code'));
      $this->messenger()->addError($message);
    }
    else {
      parent::onCancel($order, $request);
    }
  }

  /**
   * The Plaid client object used for making API calls.
   *
   * @var \ChkltLabs\Plaid\Plaid
   */
  protected $api;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The key value expirable factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected $keyValueExpirable;

  /**
   * Returns supported array of the supported country codes.
   *
   * See https://plaid.com/docs/api/tokens/#link-token-create-request-country-codes.
   *
   * @return string[]
   *   Array of country codes.
   */
  protected function getSupportedCountryCodes() {
    return [
      'US',
      'GB',
      'ES',
      'NL',
      'FR',
      'IE',
      'CA',
      'DE',
      'IT',
      'PL',
      'DK',
      'NO',
      'SE',
      'EE',
      'LT',
      'LV',
    ];
  }

  /**
   * Returns array of the supported language codes.
   *
   * See https://plaid.com/docs/api/tokens/#link-token-create-request-language.
   *
   * @return string[]
   *   The array of language codes.
   */
  protected function getSupportedLanguages() {
    return [
      'da',
      'nl',
      'en',
      'et',
      'fr',
      'de',
      'it',
      'lv',
      'lt',
      'no',
      'po',
      'ro',
      'es',
      'se',
    ];
  }

  /**
   * Log params of the request.
   *
   * @param string $reuest_name
   *   The name of the request.
   * @param array $params
   *   The params.
   */
  protected function logRequestParams(string $reuest_name, array $params) {
    $message = 'Params of the ' . $reuest_name . ' request:';
    $message .= '<br>';
    foreach ($params as $key => $value) {
      $message .= '<b>' . ltrim($key, '@') . '</b>';
      $message .= '<pre>' . $value . '</pre>';
    }
    $this->logger->debug($message, $params);
  }

  /**
   * Verify the webhook request.
   *
   * See https://plaid.com/docs/api/webhooks/webhook-verification/.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return bool
   *   Whether the webhook request is verified or not.
   */
  protected function isVerifiedWebhookRequest(Request $request) {
    try {
      $keys_storage = $this->keyValueExpirable->get('commerce_plaid_jwt_keys');
      $plaid_verification = $request->headers->get('plaid_verification');
      if (!$plaid_verification) {
        return FALSE;
      }
      [$headb64] = explode('.', $plaid_verification);
      $head = JWT::jsonDecode(JWT::urlsafeB64Decode($headb64));
      if (empty($head->alg) || $head->alg !== 'ES256' || empty($head->kid)) {
        return FALSE;
      }

      $current_key_id = $head->kid;

      // If key not in cache, update the key cache.
      if (!$keys_storage->get($current_key_id)) {
        $kids_to_update = [];
        $keys = $keys_storage->getAll();
        foreach ($keys as $key_id => $current_key) {
          if (is_null($current_key->expired_at)) {
            $kids_to_update[] = $key_id;
          }
        }

        $kids_to_update[] = $current_key_id;

        foreach ($kids_to_update as $kid) {
          $verification_key = $this->api->webhooks->getVerificationKey($kid);
          if (!empty($verification_key->key)) {
            $keys_storage->set($kid, $verification_key->key, self::JTW_EXPIRATION);
          }
        }
      }

      $current_key = $keys_storage->get($current_key_id);

      // If the key ID is not in the cache, the key ID may be invalid.
      if (!$current_key) {
        return FALSE;
      }

      // Reject expired keys.
      if (!is_null($current_key->expired_at)) {
        return FALSE;
      }

      $parsed_key = JWK::parseKey((array) $current_key);
      $decoded_jwt = JWT::decode($plaid_verification, $parsed_key);

      if (empty($decoded_jwt->iat) || empty($decoded_jwt->request_body_sha256)) {
        return FALSE;
      }

      // Verify that the webhook is not more than 5 minutes old.
      if (time() - $decoded_jwt->iat > 5 * 60) {
        return FALSE;
      }

      // Compare hashes.
      $hashed_body = hash('sha256', $request->getContent());
      if ($hashed_body === $decoded_jwt->request_body_sha256) {
        return TRUE;
      }
    }
    catch (\Exception $e) {
      watchdog_exception('commerce_plaid', $e);
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Returns error message by the error code.
   *
   * @param string $error_code
   *   The error code.
   *
   * @return string
   *   The message.
   */
  protected function getPaymentErrorMessage(string $error_code) {
    // See the Payment errors on https://plaid.com/docs/errors/.
    $errors = [
      'PAYMENT_BLOCKED' => $this->t('Payment is blocked by Plaid. Please try again.'),
      'PAYMENT_CANCELLED' => $this->t('Try making your payment again or select a different bank to continue.'),
      'PAYMENT_INSUFFICIENT_FUNDS' => $this->t("There isn't enough money in this account to complete the payment. Try again, or select another account or bank."),
      'PAYMENT_INVALID_RECIPIENT' => $this->t('The payment recipient is invalid for the selected institution. Create a new payment with a different recipient.'),
      'PAYMENT_INVALID_REFERENCE' => $this->t('The payment reference is invalid for the selected institution. Create a new payment with a different reference.'),
      'PAYMENT_INVALID_SCHEDULE' => $this->t('The payment schedule is invalid for the selected institution. Create a new payment with a different schedule.'),
      'PAYMENT_REJECTED' => $this->t('The payment was rejected by the institution. Try again, or select another account or bank.'),
      'PAYMENT_SCHEME_NOT_SUPPORTED' => $this->t('The payment scheme is not supported by the institution. Either change scheme or select another institution.'),
    ];

    return $errors[$error_code] ?? 'There were errors processing your payment on Plaid. Please, try again.';
  }

}
