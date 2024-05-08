(function (Drupal, once) {

  Drupal.behaviors.commercePlaidLink = {
    attach(context, settings) {
      // Open Plaid Link on checkout page.
      once('commerce-plaid-link', 'form.commerce-checkout-flow', context).forEach(function () {
        if (typeof settings.commercePlaid === 'undefined' ||
          settings.commercePlaid.linkToken === 'undefined' ||
          settings.commercePlaid.returnUrl === 'undefined' ||
          settings.commercePlaid.cancelUrl === 'undefined'
        ) {
          return;
        }
        localStorage.setItem('commercePlaid', JSON.stringify(settings.commercePlaid));
        const handler = Plaid.create({
          token: settings.commercePlaid.linkToken,
          onSuccess: (public_token, metadata) => {
            window.parent.location.href = settings.commercePlaid.returnUrl;
          },
          onExit: (err, metadata) => {
            let query_param = '';
            if (err !== null && err.error_code !== 'undefined' && err.error_code !== '') {
              query_param = '?error_code=' + err.error_code;
            }
            window.parent.location.href = settings.commercePlaid.cancelUrl + query_param;
            localStorage.removeItem("commercePlaid");
          },
        });
        // Open Link
        handler.open();
      });

      // Reinitialize Plaid Link after the OAuth flow.
      once('commerce-plaid-link', '.plaid-oauth-page', context).forEach(function () {
        if (!localStorage.getItem('commercePlaid')) {
          return;
        }
        var commercePlaid = JSON.parse(localStorage.getItem("commercePlaid"));
        if (commercePlaid.oauthPage === 'undefined' || window.location.pathname !== commercePlaid.oauthPage) {
          return;
        }
        if (commercePlaid.linkToken === 'undefined' || commercePlaid.returnUrl === 'undefined') {
          return;
        }
        var handler = Plaid.create({
          token: commercePlaid.linkToken,
          receivedRedirectUri: window.location.href,
          onSuccess: function (public_token) {
            window.parent.location.href = commercePlaid.returnUrl;
            localStorage.removeItem("commercePlaid");
          },
        });
        handler.open();
      });
    }
  }

})(Drupal, once);
