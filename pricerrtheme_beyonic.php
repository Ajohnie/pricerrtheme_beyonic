<?php
/*
Plugin Name: Beyonic For PricerrTheme
Plugin URI: https://github.com/Ajohnie/pricerrtheme_beyonic
Description: Adds beyonic as a payment gateway to the Pricerr Theme(version 6.3.1). It facilitates paying through mobile money
Author: Akankwatsa Johnson
Author URI: mailto:jakankwasa.tech@yahoo.com
Version: 1.0
Text Domain: beyonic_gateways
*/


add_filter('PricerrTheme_payment_methods_tabs', 'PricerrTheme_add_new_beyonic_tab');
add_filter('PricerrTheme_payment_methods_action', 'PricerrTheme_add_new_beyonic_pst');
add_filter('PricerrTheme_payment_methods_content_divs', 'PricerrTheme_add_new_beyonic_cnt');
add_filter('admin_init', 'beyonic_pricerr_temp_redir');
add_filter('PricerrTheme_withdraw_method', 'PricerrTheme_withdraw_method_beyonic');
// add withdraw form
add_action('PricerrTheme_payments_withdraw_options', 'PricerrTheme_payments_withdraw_beyonic');
// hook into deposit function
add_action('pricerrtheme_deposit_payment_gateways', 'PricerrTheme_payments_deposit_beyonic');

/**
 * function that generates a form to capture user details, phone number, amount while adding money to the e-wallet
 */
function PricerrTheme_payments_deposit_beyonic()
{
    $PricerrTheme_beyonic_enable = get_option('PricerrTheme_beyonic_enable');
    if ($PricerrTheme_beyonic_enable == "yes"):
        ?>
        <br/><br/>
        <div class="box_title3 mt-4 mb-4">
            <div class="inner-me"><?php _e('Transfer From Mobile Money', 'pricerrtheme') ?></div>
        </div>

        <table class="skf">
            <form method="post" enctype="application/x-www-form-urlencoded">
                <input type="hidden" value="<?php echo current_time('timestamp', 0) ?>"
                       name="tm_tm"/>
                <tr>
                    <td>
                        <!-- added this input to help identify beyonic payment when setting payment method (PricerrTheme_withdraw_method_beyonic())--></td>
                    <td><input value="1" type="number" contentEditable="false" hidden
                               name="PricerrTheme_beyonic_input"/></td>
                </tr>
                <tr>
                    <td><?php _e('Deposit amount:', 'pricerrtheme'); ?></td>
                    <td>
                        <input value="<?php echo isset($_POST['PricerrTheme_beyonic_input']) ? $_POST['amount10'] : ''; ?>"
                               type="number" size="10" min="1" name="amount10"
                               required/> <?php echo PricerrTheme_get_currency(); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Phone Number (in international format e.g +256701123456):', 'pricerrtheme'); ?></td>
                    <!-- using paypal since it is the variable that is saved in database(finances.php) under job_withdraws(...,payeremail,....) --->
                    <td>
                        <input value="<?php echo isset($_POST['PricerrTheme_beyonic_input']) ? $_POST['paypal'] : ''; ?>"
                               type="text" maxlength="14" minlength="10"
                               pattern="[+]{1}[0-9]{3}[0-9]{9}"
                               name="paypal" required/></td>
                </tr>
                </tr>

                <tr>
                    <td></td>
                    <td>
                        <!-- use name=withdraw10 to bypass email validation -- but should not be used with banking input-->
                        <input type="submit" name="withdraw10"
                               class="btn btn-primary green-button-me"
                               value="<?php _e('Deposit', 'pricerrtheme'); ?>"/></td>
                </tr>
            </form>
        </table>
    <?php endif;

}

/**TODO add phone no sms verification
 * function that generates a form to capture user details, phone number, amount while requesting a withdraw
 */
function PricerrTheme_payments_withdraw_beyonic()
{
    $PricerrTheme_beyonic_enable = get_option('PricerrTheme_beyonic_enable');
    if ($PricerrTheme_beyonic_enable == "yes"):
        ?>
        <br/><br/>
        <div class="box_title3 mt-4 mb-4">
            <div class="inner-me"><?php _e('Withdraw by Mobile Money', 'pricerrtheme') ?></div>
        </div>

        <table class="skf">
            <form method="post" enctype="application/x-www-form-urlencoded">
                <input type="hidden" value="<?php echo current_time('timestamp', 0) ?>"
                       name="tm_tm"/>
                <tr>
                    <td>
                        <!-- added this input to help identify beyonic payment when setting payment method (PricerrTheme_withdraw_method_beyonic())--></td>
                    <td><input value="1" type="number" contentEditable="false" hidden
                               name="PricerrTheme_beyonic_input"/></td>
                </tr>
                <tr>
                    <td><?php _e('Withdraw amount:', 'pricerrtheme'); ?></td>
                    <td>
                        <input value="<?php echo isset($_POST['PricerrTheme_beyonic_input']) ? $_POST['amount10'] : ''; ?>"
                               type="number" size="10" min="1" name="amount10"
                               required/> <?php echo PricerrTheme_get_currency(); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Phone Number (in international format e.g +256701123456):', 'pricerrtheme'); ?></td>
                    <!-- using paypal since it is the variable that is saved in database(finances.php) under job_withdraws(...,payeremail,....) --->
                    <td>
                        <input value="<?php echo isset($_POST['PricerrTheme_beyonic_input']) ? $_POST['paypal'] : ''; ?>"
                               type="text" maxlength="14" minlength="10"
                               pattern="[+]{1}[0-9]{3}[0-9]{9}"
                               name="paypal" required/></td>
                </tr>
                </tr>

                <tr>
                    <td></td>
                    <td>
                        <!-- use name=withdraw10 to bypass email validation -- but should not be used with banking input-->
                        <input type="submit" name="withdraw10"
                               class="btn btn-primary green-button-me"
                               value="<?php _e('Withdraw', 'pricerrtheme'); ?>"/></td>
                </tr>
            </form>
        </table>
    <?php endif;

}

/**
 * @param $method // withdraw method, in this case it is beyonic
 * @return string // modify method and return it
 */
function PricerrTheme_withdraw_method_beyonic($method)
{
    $PricerrTheme_beyonic_enable = get_option('PricerrTheme_beyonic_enable');
    $PricerrTheme_beyonic_post_option = $_POST['PricerrTheme_beyonic_input']; // name of unique input on withdraw form - PricerrTheme_payments_withdraw_beyonic
    if (($PricerrTheme_beyonic_enable == "yes") && isset($PricerrTheme_beyonic_post_option)) {
        $method = 'Beyonic';
    }
    return $method;
}

/**
 * @method //intercepts template redirects and checks for beyonic payments
 */
function beyonic_pricerr_temp_redir()
{
    include 'lib/Flash.php';
    Flash::show();
    if (!empty($_GET['beyonic_response_withdraw_request'])) { // response from beyonic through webhook api
        // payment was successful, update database(set withdraw request as resolved)
        global $wpdb; // link to wordpress database object
        $row_id = $_GET['beyonic_response_withdraw_request'];
        $s = "select * from " . $wpdb->prefix . "job_withdraw where id='$row_id'";
        $row = $wpdb->get_results($s);
        if (isset($row)) {
            $row = $row[0];
            $tm = current_time('timestamp', 0);
            $ss = "update " . $wpdb->prefix . "job_withdraw set done='1', datedone='$tm' where id='$row_id'";
            $wpdb->query($ss);

            // send email to admin (again -- request might have delayed at beyonic api)
            PricerrTheme_send_email_when_withdraw_completed($row->uid, $row->methods, PricerrTheme_get_show_price($row->amount));

            $reason = sprintf(__('Mobile Withdraw to %s - Details: %s', 'pricerrtheme'), $row->methods, $row->payeremail);

            // log to history again
            PricerrTheme_add_history_log('0', $reason, $row->amount, $row->uid);
            error_log('ROW ID: ' . $row_id);
            wp_redirect(get_site_url()); // redirect to any appropriate page
            exit;
        }
    }

    // payment was accepted from withdraw requests options, make payment request to beyonic api
    if (isset($_GET['page'], $_GET['tid']) && $_GET['page'] == 'withdraw-req') {
        global $wpdb; // link to wordpress database object

        $row_id = $_GET['tid'];
        // read database with passed row id
        $s = "select * from " . $wpdb->prefix . "job_withdraw where id='$row_id'";
        $row = $wpdb->get_results($s);
        $row = $row[0];
        $method = $row->methods;
        if ($row->done == 0 && $method == 'Beyonic') {
            include 'lib/Beyonic.php'; // import beyonic php lib
            $api_key = get_option('PricerrTheme_beyonic_api_key');//api key set in admin tab of beyonic
            if (!isset($api_key)) { // api key not set, so throw error
                Flash::add('error', __('Please Set Api Key In Payment Gateways under beyonic tab!', 'pricerrtheme'));
                wp_redirect(wp_get_referer());
            }
            $webHook_id = get_option('PricerrTheme_beyonic_webHook_id');//webhook id set in admin tab of beyonic
            $withdrawFee = get_option('PricerrTheme_beyonic_withdraw_fee');
            $account_id = get_option('PricerrTheme_beyonic_account_Id');//account id set in admin tab of beyonic
            $account_fname = get_option('PricerrTheme_beyonic_account_fname');
            $account_lname = get_option('PricerrTheme_beyonic_account_lname');
            if (empty($account_fname) || empty($account_lname)) {
                Flash::add('error', __('Fname And Lname Cannot Be Empty !', 'pricerrtheme'));
                wp_redirect(wp_get_referer());
            }
            if (empty($withdrawFee)) { // api key not set, so throw error
                $withdrawFee = 0;
            }

            $user = getBeyonicUser($row->uid);
            if (!isset($user)) {
                $account_fname = 'Kyeeyo'; // john
                $account_lname = 'user'; // doe
            } else {
                $account_fname = $user->display_name; // TODO add update option
                $account_lname = $user->user_nicename;
            }
            if (!isset($row->payeremail)) {
                Flash::add('error', __('Missing Phone No', 'pricerrtheme'));
                wp_redirect(wp_get_referer());
            }
            $phoneNo = validatePhoneNo($row->payeremail);
            if (!$phoneNo) {
                Flash::add('error', __('Phone No invalid or Not Supported !', 'pricerrtheme'));
                wp_redirect(wp_get_referer());
                return;
            }
            $amount = $row->amount - $withdrawFee;
            if ($amount === 0) {
                Flash::add('error', __('Amount minus withdraw Fee is Zero', 'pricerrtheme'));
                wp_redirect(wp_get_referer());
                return;
            }
            $currency = PricerrTheme_get_currency();
            $callback_url = null;
            $account = null;
            Beyonic::setApiKey($api_key);
            if (!empty($webHook_id)) {
                $hook = Beyonic_Webhook::get($webHook_id);
                if ($hook->target === null) {
                    Flash::add('error', __('Wrong Web Hook Id, Please get the correct Web Hook Id from Your Beyonic Account', 'pricerrtheme'));
                    wp_redirect(wp_get_referer());
                }
                $callback_url = $hook->target;
            }
            if (!empty($account_id)) {
                $acc = Beyonic_Account::get($account_id);
                if ($acc->id === null) {
                    Flash::add('error', __('Wrong Account Id, Please get the correct Account Id from Your Beyonic Account', 'pricerrtheme'));
                    wp_redirect(wp_get_referer());
                }
                $account = $acc;
            }
            $beyonicData = array(
                "phonenumber" => $phoneNo,
                "first_name" => $account_fname, // include if they were captured on the form
                "last_name" => $account_lname,
                "amount" => $amount,
                "currency" => $currency,
                "description" => "payment",
                "payment_type" => "money",
                "metadata" => array("id" => $row_id, 'withdrawFee' => $withdrawFee)
            );
            if (!isset($beyonicData['first_name'])) {
                $beyonicData['first_name'] = 'Kyeeyo';
            }
            if (!isset($beyonicData['last_name'])) {
                $beyonicData['last_name'] = 'user';
            }
            if ($callback_url) {
                array_merge($beyonicData, ['callback_url' => $callback_url]);
            }
            if ($account) {
                if ($account->balance < $beyonicData['amount']) {
                    Flash::add('error', __('Insufficient Account Balance, Please Add funds To the Account and try Again', 'pricerrtheme'));
                    wp_redirect(wp_get_referer());
                }
                array_merge($beyonicData, ['account' => $account->id]);
            }
            $payment = null;
            session_start();
            // check for old payments

            // check session for old payment id TODO use caching to span across sessions
            if (isset($_SESSION['oldBeyonicPayment' . $row_id])) {
                $payment = Beyonic_Payment::get($_SESSION['oldBeyonicPayment' . $row_id]);
            } else {
                // get old payment from Api
                // get one payment to estimate total records
                $counter = Beyonic_Payment::getAll(array('limit' => 1, 'offset' => 0, 'amount' => $beyonicData['amount'], 'currency' => $beyonicData['currency']));
                // get all records matching above criteria
                $oldPayments = Beyonic_Payment::getAll(array('limit' => $counter['count'], 'offset' => 0, 'amount' => $beyonicData['amount'], 'currency' => $beyonicData['currency']));
                if (count($oldPayments['results']) > 0) {
                    // filter out payments with passed row id
                    $filtered = array_filter($oldPayments['results'], static function ($p) use ($row_id) {
                        return $p->metadata->id === $row_id;
                    });
                    uasort($filtered, 'compare'); // sort according to payment id
                    $keys = array_keys($filtered); // get original array indices
                    $key = $keys[count($keys) - 1]; // extract last key which is the latest
                    $payment = $oldPayments['results'][$key];
                    $_SESSION['oldBeyonicPayment' . $row_id] = $payment->id; // write id to session to prevent future computations
                }
            }

            if ($payment) {
                Flash::add('info', __('Payment Already Queued !', 'pricerrtheme'));
                checkPayment($payment, $row_id);
            } else {
                $payment = Beyonic_Payment::create($beyonicData);
                $counter = 0;
                $pState = $payment->state;
                while ($pState == 'new') { // wait for payment status to change
                    if ($counter > 5) { // if state has not changed in 5 sec, exit the loop, leave the rest to the callback url
                        break;
                    }
                    sleep(1); // wait 1 seconds
                    $pState = Beyonic_Payment::get($payment->id)->state;
                    $counter++;
                }
                checkPayment($payment, $row_id);
            }
        }
    }

}

function checkPayment($payment, $row_id)
{
    switch ($payment->state) {
        case 'new':
            Flash::add('info', __('Payment Has Been Queued! and Is Waiting Approval', 'pricerrtheme'));
            PricerrTheme_update_beyonic_payment($row_id);
            break;
        case 'processed':
            Flash::add('info', __('Payment Completed Successfully', 'pricerrtheme'));
            PricerrTheme_update_beyonic_payment($row_id, '1');
            break;
        case 'processed_with_errors':
            Flash::add('error', __('Payment Failed!, Reason:- ' . $payment->last_error, 'pricerrtheme'));
            PricerrTheme_update_beyonic_payment($row_id);
            break;
        case 'rejected':
            Flash::add('error', __('Payment Failed!, Reason:- ' . $payment->rejected_reason, 'pricerrtheme'));
            PricerrTheme_update_beyonic_payment($row_id);
            break;
        case 'cancelled':
            Flash::add('error', __('Payment Failed!, Reason:- ' . $payment->cancelled_reason, 'pricerrtheme'));
            PricerrTheme_update_beyonic_payment($row_id);
            break;
        default:
            Flash::add('error', __('Payment Failed!, Reason:- Unknown Error Occurred While Approving', 'pricerrtheme'));
            PricerrTheme_update_beyonic_payment($row_id);
            break;
    }
}

function compare($a, $b)
{
    if ($a->id === $b->id) {
        return 0;
    }

    if ($a->id > $b->id) {
        return 1;
    }

    return -1;
}

function getBeyonicUser($uid)
{
    global $wpdb; // link to wordpress database object
    // read database with passed user id
    $s = "select * from " . $wpdb->prefix . "users where id='$uid'";
    $row = $wpdb->get_results($s);
    if (is_array($row)) {
        return $row[0];
    }
    return $row;
}

/** function that validates phone no passed from database to prevent verification errors from beyonic
 * @param $phoneNo
 * @return bool|string|string[]
 *
 */
function validatePhoneNo($phoneNo)
{
    $phoneNo = trim($phoneNo);
    $phoneNo = str_replace(['(', ')', '-'], '', $phoneNo);
    // $regex = '/^[+]{0,1}[(]{0,1}[0-9]{3}[)]{0,1}[0-9]{9}|[+]{0,1}[(]{0,1}[0-9]{3}[)]{0,1}[-]{0,1}[0-9]{9}|[+]{0,1}[(]{0,1}[0-9]{3}[)]{0,1}[-]{0,1}[0-9]{3}[-]{0,1}[0-9]{3}[-]{0,1}[0-9]{3}[-]{0,1}$/m';;
    $regex_simple = '/^[+]{1}[0-9]{3}[0-9]{9}$/m'; // only support one format
    $verify = static function ($phnNo) use ($regex_simple) {
        $match = preg_match($regex_simple, $phnNo);
        return strlen($phnNo) >= 10 && strlen($phnNo) <= 14 && $match;
    };
    if (!$verify($phoneNo)) {
        return false;
    }
    return $phoneNo;
}

/**
 * @method // updates database with passed details
 * @param $row_id , id of the payment request
 * @param string $done , marker for whether the payment was accepted
 */
function PricerrTheme_update_beyonic_payment($row_id, $done = '0')
{
    global $wpdb; // link to wordpress database object
    switch ($done) {
        case '0':
            $ss = "update " . $wpdb->prefix . "job_withdraw set done=" . $done . ", rejected_on='0', rejected='0', datedone='0' where id='$row_id'";
            $wpdb->query($ss);
            break;
        case '1':
            $tm = current_time('timestamp', 0);
            $ss = "update " . $wpdb->prefix . "job_withdraw set done=" . $done . ", datedone='$tm' where id='$row_id'";
            $wpdb->query($ss);
            break;
        default:
            break;
    }
    $wpdb->close();
    wp_redirect(wp_get_referer());
}

/**
 * @method // updates beyonic configuration options in the beyonic tab in the payment gateway options
 */
function PricerrTheme_add_new_beyonic_pst()
{

    if (isset($_POST['PricerrTheme_save_beyonic'])):
        $PricerrTheme_beyonic_api_key = trim($_POST['PricerrTheme_beyonic_api_key']);
        $PricerrTheme_beyonic_enable = $_POST['PricerrTheme_beyonic_enable'];
        $PricerrTheme_beyonic_withdraw_fee = $_POST['PricerrTheme_beyonic_withdraw_fee'];
        $PricerrTheme_beyonic_webHook_Id = $_POST['PricerrTheme_beyonic_webHook_Id'];
        $PricerrTheme_beyonic_account_Id = $_POST['PricerrTheme_beyonic_account_Id'];
        update_option('PricerrTheme_beyonic_api_key', $PricerrTheme_beyonic_api_key);
        update_option('PricerrTheme_beyonic_enable', $PricerrTheme_beyonic_enable);
        update_option('PricerrTheme_beyonic_withdraw_fee', $PricerrTheme_beyonic_withdraw_fee);
        update_option('PricerrTheme_beyonic_webHook_Id', $PricerrTheme_beyonic_webHook_Id);
        update_option('PricerrTheme_beyonic_account_Id', $PricerrTheme_beyonic_account_Id);
    endif;
}


/**
 * @method //adds html code for beyonic tab header
 */
function PricerrTheme_add_new_beyonic_tab()
{
    ?>

    <li><a href="#tabs_beyonic">Beyonic</a></li>

    <?php

}


/**
 * @method //adds html code for the beyonic tab body under payment gateways in the admin menu
 */
function PricerrTheme_add_new_beyonic_cnt()
{
    $arr = array("yes" => __("Yes", 'PricerrTheme'), "no" => __("No", 'PricerrTheme'));

    ?>

    <div id="tabs_beyonic">
        <!-- TODO properly handle post request-->
        <form method="post"
              action="<?php bloginfo('siteurl'); ?>/wp-admin/admin.php?page=payment-methods&active_tab=tabs_beyonic">
            <table width="100%" class="sitemile-table">

                <tr>
                    <td valign=top
                        width="22"><?php PricerrTheme_theme_bullet('enable or disable beyonic payments'); ?></td>
                    <td width="200"><?php _e('Enable:', 'PricerrTheme'); ?></td>
                    <td><?php echo PricerrTheme_get_option_drop_down($arr, 'PricerrTheme_beyonic_enable'); ?></td>
                </tr>
                <tr>
                    <td valign=top
                        width="22"><?php PricerrTheme_theme_bullet('api key you received from your beyonic account'); ?></td>
                    <td><?php _e('Api Key:', 'PricerrTheme'); ?></td>
                    <td><input type="text" size="45" name="PricerrTheme_beyonic_api_key"
                               value="<?php echo get_option('PricerrTheme_beyonic_api_key'); ?>"/></td>
                </tr>
                <tr>
                    <td valign=top width="22"><?php PricerrTheme_theme_bullet('withdraw fee'); ?></td>
                    <td><?php _e('Withdraw Fee:', 'PricerrTheme'); ?></td>
                    <td><input type="number" min="0" name="PricerrTheme_beyonic_withdraw_fee"
                               value="<?php echo get_option('PricerrTheme_beyonic_withdraw_fee'); ?>"/></td>
                </tr>
                <tr>
                    <td valign=top
                        width="22"><?php PricerrTheme_theme_bullet('web hook Id from your beyonic account'); ?></td>
                    <td><?php _e('Web Hook Id:', 'PricerrTheme'); ?></td>
                    <td><input type="number" min="0" name="PricerrTheme_beyonic_webHook_Id"
                               value="<?php echo get_option('PricerrTheme_beyonic_webHook_Id'); ?>"/></td>
                </tr>
                <tr>
                    <td valign=top
                        width="22"><?php PricerrTheme_theme_bullet('Account Id from your beyonic account'); ?></td>
                    <td><?php _e('Account Id:', 'PricerrTheme'); ?></td>
                    <td><input type="number" min="0" name="PricerrTheme_beyonic_account_Id"
                               value="<?php echo get_option('PricerrTheme_beyonic_account_Id'); ?>"/></td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td><input type="submit" name="PricerrTheme_save_beyonic"
                               value="<?php _e('Save Options', 'PricerrTheme'); ?>"/></td>
                </tr>

            </table>
        </form>

    </div>

    <?php

}

?>
