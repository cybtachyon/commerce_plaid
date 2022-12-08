#Pre-requirements
You should have Plaid account.
Contact Plaid support and request access to Payment Initiation product.

# Setup
1. Enable the commerce_plaid.
2. Add new payment gateway on the page /admin/commerce/config/payment-gateways
3. Select Plaid as plugin.
4. Input client_id and secret_key from your Plaid's account.
5. Input IBAN or BACS of the recipient.
6. Select Mode and Country codes.
7. If testing the module on local you will need to fill in the field Webhook base URL. See next section.
8. Add [your-domain]/plaid-oauth-page to 'Allowed redirect URIs' on the page https://dashboard.plaid.com/team/api.
   Even if you're testing the module on your local environment, you have to add the path with your local domain e.g. https://mysite.ddev.site/plaid-oauth-page.

# Testing webhooks on local environment by using ddev and ngrok.
1. Setup local site by using ddev https://ddev.readthedocs.io/en/latest/users/quickstart/.
2. Install ngrok on your system https://ngrok.com/download. Be sure to sign-up to ngrok and add auth token 'ngrok config add-authtoken <token>' on your system.
3. In root folder of your project run 'ddev share'. You should see message with 'Session Status online' and several other lines.
4. Copy URL from Forwarding line.
5. Use the URL in field 'Webhook base URL' on the Plaid payment gateway's settings page. Enable also 'Log API calls'. Submit the settings form. Don't forget to update the URL after each restart of the ngrok ('ddev share').
6. Test checkout with Plaid payment gateway and check the Recent log messages in admin UI of the site.
7. You should find messages like "Plaid webhook request: ...".

# Dependencies
The module requires firebase/php-jwt minimum version 6.3.0 as in this version support of the EC key type was added.
See https://github.com/firebase/php-jwt/pull/399.

If the site uses package imbo/behat-api-extension, there can be conflict between required versions of firebase/php-jwt.
There is related PR https://github.com/imbo/behat-api-extension/pull/121#pullrequestreview-1087665420.

As temp fix we can remove imbo/behat-api-extension and install the latest version of the firebase/php-jwt
or manually change line in composer.lock in 'require' section under "name": "imbo/behat-api-extension" to "firebase/php-jwt": "^4.0 | ^5.0 | ^6.0".
