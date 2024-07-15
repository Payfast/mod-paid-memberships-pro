# mod-paid-memberships-pro

## This plugin is deprecated

**This repository is no longer supported and has been archived.** Please switch to the official
plugin [Paid Memberships Pro – Payfast Gateway Add On](https://wordpress.org/plugins/pmpro-payfast/)
by [Paid Memberships Pro](https://www.paidmembershipspro.com/).

## Payfast module v1.3.0 for Paid Memberships Pro v3.0.4

This is the Payfast module for Paid Memberships Pro. Please feel free
to [contact the Payfast support team](https://payfast.io/contact/) should you require any assistance.

## Installation

1. Unzip the module to a temporary location on your computer.
2. Copy the **wp-content** folder in the archive to your base WordPress folder.
    - This will merge the folders in the Payfast module with your WordPress folders.
    - You will be prompted to overwrite the paymentsettings.php file, select overwrite.
3. At this point it is necessary to add 2 lines of code to the paid-memberships-pro.php file which is in the root
   directory of the Paid Memberships Pro plugin. Open the file in a text editor and on **line 84** add the following
   code:

```
   require_once(PMPRO_DIR . "/classes/gateways/class.pmprogateway_payfast.php");
```

4. Go to the end of **line 186**, push enter to create a new line 187, add the following on 187:

```
   'payfast' => __('Payfast', 'pmpro'),
```

5. Log in to the administration console of your website.
6. Select **Memberships** from the menu, and go to **Payment Settings**.
7. Under **Payment Settings** select **Payment Gateway and SSL**.
8. Choose **Payfast** from the **Payment Gateway** drop down menu.
9. The Payfast options will then be shown below.
10. Leave everything else as per default and click **Save Changes**.
11. The module is now ready to be tested in sandbox (note: sandbox does not currently work for subscriptions).
12. When ready to go live input your Payfast merchant ID and Key (and passphrase if it is set on your Payfast account)
    and click **Save Changes**.
13. To setup a subscription select **Recurring Subscription**. Currently Paid Memberships Pro can only accept monthly
    and
    annual subscriptions through Payfast.
    - NOTE: If you have auto-renewal settings, this must be set to **No. All checkouts will setup recurring billing.**
      It is
      not advisable to set an expiry date with the set expiry date plugin, rather set the cycles to the required number,
      or manage the subscription from the Payfast account dashboard.
14. Ensure that subscriptions are enabled on the Integration page (under Settings) on your Payfast account.

Please [click here](https://payfast.io/integration/plugins/paid-memberships-pro/) for more information concerning this
module.

## Collaboration

Please submit pull requests with any tweaks, features or fixes you would like to share.
