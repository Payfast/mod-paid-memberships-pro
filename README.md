# paid-memberships-pro-1_8

Copyright (c) 2015 PayFast (Pty) Ltd

LICENSE:

This payment module is free software; you can redistribute it and/or modify it under the terms of the GNU Lesser General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.

This payment module is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public License for more details.

Please see http://www.opensource.org/licenses/ for a copy of the GNU Lesser General Public License.

INTEGRATION INSTRUCTIONS:
1. Unzip the module to a temporary location on your computer.
2. Copy the 'wp-content' folder in the archive to your base WordPress folder
 -This will merge the folders in the PayFast module with your WordPress folders
 -You will be prompted to overwrite the paymentsettings.php file, select overwrite
3. At this point it is necessary to add 2 lines of code to the  paid-memberships-pro.php file which is in the root directory of the Paid Memberships Pro plugin
 -Open the file in a text editor and on line 84 add the following code:
 require_once(PMPRO_DIR . "/classes/gateways/class.pmprogateway_payfast.php");
 -Go to the end of line 133, push enter to create a new line 118, add the following on 118:
 'payfast' => __('PayFast', 'pmpro'),
4. Log in to the administration console of your website
5. Select “Memberships” from the menu, and go to “Payment Settings”
6. Under “Payment Settings” select “Payment Gateway and SSL”
7. Choose “PayFast” from the “Payment Gateway” drop down menu
8. The PayFast options will then be shown below
9. Leave everything else as per default and click "Save Changes"
10. The module is now ready to be tested in sandbox
11. When ready to go live input your PayFast merchant ID and Key (and passphrase if it is set on your PayFast account) and click "Save Changes"