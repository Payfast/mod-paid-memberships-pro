# paid-memberships-pro

INTEGRATION INSTRUCTIONS:
1. Unzip the module to a temporary location on your computer.
2. Copy the 'wp-content' folder in the archive to your base WordPress folder
 -This will merge the folders in the PayFast module with your WordPress folders
 -You will be prompted to overwrite the paymentsettings.php file, select overwrite
3. At this point it is necessary to add 2 lines of code to the  paid-memberships-pro.php file which is in the root directory of the Paid Memberships Pro plugin
 -Open the file in a text editor and on line 84 add the following code:
 require_once(PMPRO_DIR . "/classes/gateways/class.pmprogateway_payfast.php");
 -Go to the end of line 133, push enter to create a new line 134, add the following on 134:
 'payfast' => __('PayFast', 'pmpro'),
4. Log in to the administration console of your website
5. Select “Memberships” from the menu, and go to “Payment Settings”
6. Under “Payment Settings” select “Payment Gateway and SSL”
7. Choose “PayFast” from the “Payment Gateway” drop down menu
8. The PayFast options will then be shown below
9. Leave everything else as per default and click "Save Changes"
10. The module is now ready to be tested in sandbox (note: sandbox does not currently work for subscriptions)
11. When ready to go live input your PayFast merchant ID and Key (and passphrase if it is set on your PayFast account) and click "Save Changes"
12. To setup a subscription select ‘Recurring Subscription’. Currently Paid Memberships Pro can only accept monthly and annual subscriptions through PayFast
    NOTE: If you have auto-renewal settings, this must be set to ‘No. All checkouts will setup recurring billing.’ It is not advisable to set an expiry date with the set expiry date plugin, rather set the cycles to the required number, or manage the subscription from the PayFast account dashboard
13. Ensure that subscriptions are enabled on the Integration page (under Settings) on your PayFast account

Please [click here](https://payfast.io/integration/shopping-carts/paid-memberships-pro/) for more information concerning this module.
