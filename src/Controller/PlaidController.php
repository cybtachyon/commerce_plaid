<?php

namespace Drupal\commerce_plaid\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides callbacks for the commerce_plaid routes.
 */
class PlaidController extends ControllerBase {

  /**
   * Returns empty content page for the Plaid Oauth.
   *
   * See https://plaid.com/docs/link/oauth/#create-and-register-a-redirect-uri.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The renderable array.
   */
  public function plaidOauthPage(Request $request) {
    $build = [];
    $build['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#atttibutes' => [
        'class' => ['plaid-oauth-page'],
      ],
      '#value' => '',
    ];
    $build['#attached']['library'][] = 'commerce_plaid/plaid_link';

    return $build;
  }

}
