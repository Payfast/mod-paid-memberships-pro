<?php
/**
 * payfast_itn_handler.php
 *
 */

//in case the file is loaded directly
if (!defined("WP_USE_THEMES")) {
    global $isapage;
    $isapage = true;

    define('WP_USE_THEMES', false);
    require_once dirname(__FILE__) . '/../../../../wp-load.php';
}
require_once dirname(__FILE__) . '/../includes/lib/Payfast/vendor/autoload.php';

use Payfast\PayfastCommon\PayfastCommon;

//some globals
global $wpdb, $gateway_environment, $logstr;

$payfastCommon = new PayfastCommon(pmpro_getOption("payfast_debug") === '1');
// Variable Initialization
$pfError       = false;
$pfErrMsg      = '';
$pfDone        = false;
$pfData        = array();
$pfHost        = (($gateway_environment == 'sandbox') ? 'sandbox' : 'www') . '.payfast.co.za';
$pfOrderId     = '';
$pfParamString = '';


$payfastCommon->pflog('Payfast ITN call received');

//// Notify Payfast that information has been received
if (!$pfError && !$pfDone) {
    header('HTTP/1.0 200 OK');
    flush();
}

//// Get data sent by Payfast
if (!$pfError && !$pfDone) {
    $payfastCommon->pflog('Get posted data');

    // Posted variables from ITN
    $pfData = $payfastCommon->pfGetData();

    $morder = new MemberOrder($pfData['m_payment_id']);
    $morder->getMembershipLevel();
    $morder->getUser();
    $payfastCommon->pflog('Payfast Data: ' . print_r($pfData, true));

    if ($pfData === false) {
        $pfError  = true;
        $pfErrMsg = PF_ERR_BAD_ACCESS;
    }
}

//// Verify security signature
if (!$pfError && !$pfDone) {
    $payfastCommon->pflog('Verify security signature');

    $passPhrase   = pmpro_getOption('payfast_passphrase');
    $pfPassPhrase = empty($passPhrase) ? null : $passPhrase;

    // If signature different, log for debugging
    if (!$payfastCommon->pfValidSignature($pfData, $pfParamString, $pfPassPhrase)) {
        $pfError  = true;
        $pfErrMsg = $payfastCommon->PF_ERR_INVALID_SIGNATURE;
    }
}

//// Verify data received
$payfastCommon->pflog('Verify data received');
$moduleInfo = [
    "pfSoftwareName"       => 'Paid Membership Pro',
    "pfSoftwareVer"        => '3.0.4',
    "pfSoftwareModuleName" => 'Payfast-PaidMembershipPro',
    "pfModuleVer"          => '1.3.0',
];

$pfValid = $payfastCommon->pfValidData($moduleInfo, $pfHost, $pfParamString);

if ($pfValid) {
    $payfastCommon->pflog("ITN message successfully verified by Payfast");
} else {
    $pfError  = true;
    $pfErrMsg = $payfastCommon->PF_ERR_BAD_ACCESS;
}

//// Check data against internal order
if (!$pfError && !$pfDone && $pfData['payment_status'] == 'COMPLETE') {
    if (empty($pfData['token']) || strtotime($pfData['custom_str1']) <= strtotime(gmdate('Y-m-d') . '+ 2 days')) {
        $checkTotal = $morder->total;
    }
    if (!empty($pfData['token']) && strtotime(gmdate('Y-m-d')) > strtotime($pfData['custom_str1'] . '+ 2 days')) {
        $checkTotal = $morder->subtotal;
    }

    if (!$payfastCommon->pfAmountsEqual($pfData['amount_gross'], $checkTotal)) {
        $payfastCommon->pflog('Amount Returned: ' . $pfData['amount_gross'] . "\n Amount in Cart:" . $checkTotal);
        $pfError  = true;
        $pfErrMsg = $payfastCommon->PF_ERR_AMOUNT_MISMATCH;
    }
}

//// Check status and update order
if (!$pfError && !$pfDone) {
    if ($pfData['payment_status'] == 'COMPLETE' && !empty($pfData['token'])) {
        $txn_id    = $pfData['m_payment_id'];
        $subscr_id = $pfData['token'];
        if (strtotime($pfData['custom_str1']) <= strtotime(gmdate('Y-m-d') . '+ 2 days')) {
            //if there is no amount1, this membership has a trial, and we need to update membership/etc
            $amount = $pfData['amount_gross'];

            if (true) {
                //trial, get the order
                $morder               = new MemberOrder($pfData['m_payment_id']);
                $morder->paypal_token = $pfData['token'];
                $morder->getMembershipLevel();
                $morder->getUser();

                //no txn_id on these, so let's use the subscr_id
                $txn_id = $pfData['m_payment_id'];

                //update membership
                if (pmpro_itnChangeMembershipLevel($txn_id, $morder, $payfastCommon)) {
                    $payfastCommon->pflog("Checkout processed (" . $morder->code . ") success!");
                } else {
                    $payfastCommon->pflog("ERROR: Couldn't change level for order (" . $morder->code . ").");
                }
            } else {
                //we're ignoring this. we will get a payment notice from IPN and process that
                $payfastCommon->pflog("Going to wait for the first payment to go through.");
            }
        }

        //Payfast Standard Subscription Payment
        if (strtotime(gmdate('Y-m-d')) > strtotime($pfData['custom_str1'] . '+ 2 days') && !empty($pfData['token'])) {
            $last_subscr_order = new MemberOrder();

            if ($last_subscr_order->getLastMemberOrderBySubscriptionTransactionID($pfData['m_payment_id'])) {
                $last_subscr_order->paypal_token = $pfData['token'];
                //subscription payment, completed or failure?
                if ($pfData['payment_status'] == "COMPLETE") {
                    pmpro_ipnSaveOrder($pfData['pf_payment_id'], $last_subscr_order, $payfastCommon);
                } else {
                    pmpro_ipnFailedPayment($last_subscr_order);
                }
            } else {
                $payfastCommon->pflog(
                    "ERROR: Couldn't find last order for this recurring payment (" . $pfData['m_payment_id'] . ")."
                );
            }
        } else {
            //subscription payment, completed or failure?
            if ($pfData['payment_status'] == "COMPLETE") {
                pmpro_ipnSaveOrder($pfData['m_payment_id'], $last_subscr_order, $payfastCommon);
                $payfastCommon->pflog('subscription payment for subscription id: ' . print_r($last_subscr_order, true));
            } elseif ($_POST['payment_status'] == "subscription_failed") {
                pmpro_ipnFailedPayment($last_subscr_order);
            } else {
                $payfastCommon->pflog('Payment status is ' . $_POST['payment_status'] . '.');
            }
        }
    }
}

if ($pfData['payment_status'] == 'CANCELLED') {
    //find last order
    $last_subscr_order = new MemberOrder();
    if (!($last_subscr_order->getLastMemberOrderBySubscriptionTransactionID($pfData['m_payment_id']))) {
        $payfastCommon->pflog(
            "ERROR: Couldn't find this order to cancel (subscription_transaction_id=" . $pfData['m_payment_id'] . ")."
        );
    } else {
        //found order, let's cancel the membership
        $user = get_userdata($last_subscr_order->user_id);

        if (empty($user) || empty($user->ID)) {
            $payfastCommon->pflog(
                "ERROR: Could not cancel membership. No user attached to order #" . $last_subscr_order->id
                . " with subscription transaction id = " . $recurring_payment_id . "."
            );
        } else {
            if ($last_subscr_order->status == "cancelled") {
                $payfastCommon->pflog(
                    "We've already processed this cancellation. Probably originated from WP/PMPro. (Order #"
                    . $last_subscr_order->id . ", Subscription Transaction ID #" . $pfData['m_payment_id'] . ")"
                );
            } elseif (!pmpro_hasMembershipLevel($last_subsc_order->membership_id, $user->ID)) {
                $payfastCommon->pflog(
                    "This user has a different level than the one associated with this order."
                    . "Their membership was probably changed by an admin or through an upgrade/downgrade. (Order #"
                    . $last_subscr_order->id . ", Subscription Transaction ID #" . $pfData['m_payment_id'] . ")"
                );
            } else {
                //if the initial payment failed, cancel with status error instead of cancelled
                if ($initial_payment_status === "Failed") {
                    pmpro_changeMembershipLevel(0, $last_subscr_order->user_id, 'error');
                } else {
                    $last_subscr_order->updateStatus("cancelled");

                    global $wpdb;
                    $query = "UPDATE $wpdb->pmpro_memberships_orders SET status = 'cancelled' WHERE subscription_transaction_id = " . $pfData['m_payment_id'];
                    $wpdb->query($query);

                    $sqlQuery = "UPDATE $wpdb->pmpro_memberships_users SET status = 'cancelled' WHERE user_id = '"
                                . $last_subscr_order->user_id . "' AND membership_id = '"
                                . $last_subscr_order->membership_id . "' AND status = 'active'";
                    $wpdb->query($sqlQuery);
                }

                $payfastCommon->pflog(
                    "Cancelled membership for user with id = " . $last_subscr_order->user_id
                    . ". Subscription transaction id = " . $pfData['m_payment_id'] . "."
                );

                //send an email to the member
                $myemail = new PMProEmail();
                $myemail->sendCancelEmail($user);

                //send an email to the admin
                $myemail = new PMProEmail();
                $myemail->sendCancelAdminEmail($user, $last_subscr_order->membership_id);
            }
        }
    }
}


$payfastCommon->pflog('Check status and update order');

$transaction_id = $pfData['pf_payment_id'];
$morder         = new MemberOrder($pfData['m_payment_id']);
$morder->getMembershipLevel();
$morder->getUser();

if (empty($pfData['token'])) {
    switch ($pfData['payment_status']) {
        case 'COMPLETE':
            $morder = new MemberOrder($pfData['m_payment_id']);
            $morder->getMembershipLevel();
            $morder->getUser();

            //update membership
            if (pmpro_itnChangeMembershipLevel($transaction_id, $morder, $payfastCommon)) {
                $payfastCommon->pflog("Checkout processed (" . $morder->code . ") success!");
            } else {
                $payfastCommon->pflog("ERROR: Couldn't change level for order (" . $morder->code . ").");
            }

            break;

        case 'FAILED':
            $payfastCommon->pflog("ERROR: ITN from Payfast for order (" . $morder->code . ") Failed.");
            break;

        case 'PENDING':
            $payfastCommon->pflog("ERROR: ITN from Payfast for order (" . $morder->code . ") Pending.");

            break;

        default:
            $payfastCommon->pflog("ERROR: Unknown error for order (" . $morder->code . ").");
            break;
    }
}


// If an error occurred
if ($pfError) {
    $payfastCommon->pflog('Error occurred: ' . $pfErrMsg);
}


/*
    Change the membership level. We also update the membership order to include filtered valus.
*/
function pmpro_itnChangeMembershipLevel($txn_id, &$morder, PayfastCommon $payfastCommon)
{
    global $wpdb;
    //filter for level
    $morder->membership_level = apply_filters("pmpro_ipnhandler_level", $morder->membership_level, $morder->user_id);

    //fix expiration date
    if (!empty($morder->membership_level->expiration_number)) {
        $enddate = "'" . date(
                "Y-m-d",
                strtotime(
                    "+ " . $morder->membership_level->expiration_number
                    . " " . $morder->membership_level->expiration_period
                )
            ) . "'";
    } else {
        $enddate = "NULL";
    }

    //get discount code     (NOTE: but discount_code isn't set here. How to handle discount codes for PayPal Standard?)
    $morder->getDiscountCode();
    $discount_code_id = getCodeId($morder);

    //set the start date to current_time('timestamp') but allow filters
    $startdate = apply_filters(
        "pmpro_checkout_start_date",
        "'" . current_time('mysql') . "'",
        $morder->user_id,
        $morder->membership_level
    );

    //custom level to change user to
    $custom_level = array(
        'user_id'         => $morder->user_id,
        'membership_id'   => $morder->membership_level->id,
        'code_id'         => $discount_code_id,
        'initial_payment' => $morder->membership_level->initial_payment,
        'billing_amount'  => $morder->membership_level->billing_amount,
        'cycle_number'    => $morder->membership_level->cycle_number,
        'cycle_period'    => $morder->membership_level->cycle_period,
        'billing_limit'   => $morder->membership_level->billing_limit,
        'trial_amount'    => $morder->membership_level->trial_amount,
        'trial_limit'     => $morder->membership_level->trial_limit,
        'startdate'       => $startdate,
        'enddate'         => $enddate
    );

    global $pmpro_error;
    if (!empty($pmpro_error)) {
        echo $pmpro_error;
        $payfastCommon->pflog($pmpro_error);
    }

    //change level and continue "checkout"
    if (pmpro_changeMembershipLevel($custom_level, $morder->user_id) !== false) {
        //update order status and transaction ids
        $morder->status                 = "success";
        $morder->payment_transaction_id = $txn_id;
        if (!empty($_POST['token'])) {
            $morder->subscription_transaction_id = $_POST['m_payment_id'];
        } else {
            $morder->subscription_transaction_id = "";
        }
        $morder->saveOrder();

        //add discount code use
        if (!empty($discount_code) && !empty($use_discount_code)) {
            $wpdb->query(
                "INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $morder->user_id . "', '" . $morder->id . "', '" . current_time(
                    'mysql'
                ) . ""
            );
        }

        //save first and last name fields
        saveFirstAndLastNameFields($morder);

        //hook
        do_action("pmpro_after_checkout", $morder->user_id, $morder);

        //setup some values for the emails
        if (!empty($morder)) {
            $invoice = new MemberOrder($morder->id);
        } else {
            $invoice = null;
        }

        $user                   = get_userdata($morder->user_id);
        $user->membership_level = $morder->membership_level;        //make sure they have the right level info

        //send email to member
        $pmproemail = new PMProEmail();
        $pmproemail->sendCheckoutEmail($user, $invoice);

        //send email to admin
        $pmproemail = new PMProEmail();
        $pmproemail->sendCheckoutAdminEmail($user, $invoice);


        // cancel order previous Payfast subscription if applicable
        $oldSub = $wpdb->get_var(
            "SELECT paypal_token FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $_POST['custom_int1'] . "' AND status = 'cancelled' ORDER BY timestamp DESC LIMIT 1"
        );

        return true;
    } else {
        return false;
    }
}

/**
 * @param $morder
 *
 * @return void
 */
function saveFirstAndLastNameFields($morder): void
{
    if (!empty($_POST['first_name'])) {
        $old_firstname = get_user_meta($morder->user_id, "first_name", true);
        if (!empty($old_firstname)) {
            update_user_meta($morder->user_id, "first_name", $_POST['first_name']);
        }
    }
    if (!empty($_POST['last_name'])) {
        $old_lastname = get_user_meta($morder->user_id, "last_name", true);
        if (!empty($old_lastname)) {
            update_user_meta($morder->user_id, "last_name", $_POST['last_name']);
        }
    }
}

/**
 * @param $morder
 *
 * @return mixed|string
 */
function getCodeId($morder): mixed
{
    if (!empty($morder->discount_code)) {
        //update membership level
        $morder->getMembershipLevel(true);
        $discount_code_id = $morder->discount_code->id;
    } else {
        $discount_code_id = "";
    }

    return $discount_code_id;
}


function pmpro_ipnSaveOrder($txn_id, $last_order, PayfastCommon $payfastCommon)
{
    global $wpdb;

    //check that txn_id has not been previously processed
    $old_txn = $wpdb->get_var(
        "SELECT payment_transaction_id FROM $wpdb->pmpro_membership_orders WHERE payment_transaction_id = '" . $txn_id . "' LIMIT 1"
    );

    if (empty($old_txn)) {
        //hook for successful subscription payments
        //    do_action("pmpro_subscription_payment_completed");

        //save order
        $morder                              = new MemberOrder();
        $morder->user_id                     = $last_order->user_id;
        $morder->membership_id               = $last_order->membership_id;
        $morder->payment_transaction_id      = $txn_id;
        $morder->subscription_transaction_id = $last_order->subscription_transaction_id;
        $morder->gateway                     = $last_order->gateway;
        $morder->gateway_environment         = $last_order->gateway_environment;
        $morder->paypal_token                = $last_order->paypal_token;

        // Payment Status
        $morder->status = 'success'; // We have confirmed that and thats the reason we are here.
        // Payment Type.
        $morder->payment_type = $last_order->payment_type;

        //set amount based on which PayPal type
        if ($last_order->gateway == "payfast") {
            $morder->InitialPayment = $_POST['amount_gross']; //not the initial payment, but the class is expecting that
            $morder->PaymentAmount  = $_POST['amount_gross'];
        }

        $morder->FirstName = $_POST['name_first'];
        $morder->LastName  = $_POST['name_last'];
        $morder->Email     = $_POST['email_address'];

        //get address info if appropriate
        if ($last_order->gateway == "payfast") {
            $morder->Address1    = get_user_meta($last_order->user_id, "pmpro_baddress1", true);
            $morder->City        = get_user_meta($last_order->user_id, "pmpro_bcity", true);
            $morder->State       = get_user_meta($last_order->user_id, "pmpro_bstate", true);
            $morder->CountryCode = "ZA";
            $morder->Zip         = get_user_meta($last_order->user_id, "pmpro_bzip", true);
            $morder->PhoneNumber = get_user_meta($last_order->user_id, "pmpro_bphone", true);

            $morder->billing->name    = $_POST['name_first'] . " " . $_POST['name_last'];
            $morder->billing->street  = get_user_meta($last_order->user_id, "pmpro_baddress1", true);
            $morder->billing->city    = get_user_meta($last_order->user_id, "pmpro_bcity", true);
            $morder->billing->state   = get_user_meta($last_order->user_id, "pmpro_bstate", true);
            $morder->billing->zip     = get_user_meta($last_order->user_id, "pmpro_bzip", true);
            $morder->billing->country = get_user_meta($last_order->user_id, "pmpro_bcountry", true);
            $morder->billing->phone   = get_user_meta($last_order->user_id, "pmpro_bphone", true);

            //get CC info that is on file
            $morder->cardtype              = get_user_meta($last_order->user_id, "pmpro_CardType", true);
            $morder->accountnumber         = hideCardNumber(
                get_user_meta($last_order->user_id, "pmpro_AccountNumber", true),
                false
            );
            $morder->expirationmonth       = get_user_meta($last_order->user_id, "pmpro_ExpirationMonth", true);
            $morder->expirationyear        = get_user_meta($last_order->user_id, "pmpro_ExpirationYear", true);
            $morder->ExpirationDate        = $morder->expirationmonth . $morder->expirationyear;
            $morder->ExpirationDate_YdashM = $morder->expirationyear . "-" . $morder->expirationmonth;
        }

        //save
        $morder->saveOrder();
        $morder->getMemberOrderByID($morder->id);

        //email the user their invoice
        $pmproemail = new PMProEmail();
        $pmproemail->sendInvoiceEmail(get_userdata($last_order->user_id), $morder);

        do_action("pmpro_subscription_payment_completed", $morder);

        $payfastCommon->pflog("New order (" . $morder->code . ") created.");

        return true;
    } else {
        $payfastCommon->pflog("Duplicate Transaction ID: " . $txn_id);

        return true;
    }
}


