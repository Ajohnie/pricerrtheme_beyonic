<?php
/*
Plugin Name: PricerrTheme Beyonic
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
add_action('PricerrTheme_payments_withdraw_options', 'PricerrTheme_payments_withdraw_beyonic'); // add withdraw form


/**
 * @return string //regular expression used to validate phone No TODO validate phone No
 */
function PricerrTheme_get_phoneNo_mask_beyonic()
{
    $regex = trim(get_option('PricerrTheme_phoneNo_mask_beyonic')); // add option to beyonic payments form
    if (empty($regex)) return ''; // default mask if non is passed
    return $regex;

}

/**
 * function that generates a form to capture user details, phone number, amount
 */
function PricerrTheme_payments_withdraw_beyonic()
{
    $PricerrTheme_beyonic_enable = get_option('PricerrTheme_beyonic_enable');
    if ($PricerrTheme_beyonic_enable == "yes"):
        ?>
        <br/><br/>

        <div class="box_title3 mt-4 mb-4">
            <div class="inner-me"><?php _e('Withdraw by beyonic', 'pricerrtheme') ?></div>
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
                    <!-- TODO add phone No pattern-->
                    <!-- using paypal since it is the variable that is saved in database(finances.php) under job_withdraws(...,payeremail,....) --->
                    <td>
                        <input value="<?php echo isset($_POST['PricerrTheme_beyonic_input']) ? $_POST['paypal'] : ''; ?>"
                               type="text" maxlength="14" minlength="10"
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
        // TODO validate phone no here
    }
    return $method;
}

/**
 * @method //intercepts template redirects and checks for beyonic payments
 */
function beyonic_pricerr_temp_redir()
{
    if (!empty($_GET['beyonic_response_withdraw_request'])) { // response from beyonic through webhook api
        // payment was successful, update database(set withdraw request as resolved)
        global $wpdb; // link to wordpress database object
        $row_id = $_GET['beyonic_response_withdraw_request'];
        $s = "select * from " . $wpdb->prefix . "job_withdraw where id='$row_id'";
        $row = $wpdb->get_results($s); //or die(mysqli_error(null)); TODO catch mysql errors
        $row = $row[0];
        $tm = current_time('timestamp', 0);
        $ss = "update " . $wpdb->prefix . "job_withdraw set done='1', datedone='$tm' where id='$row_id'";
        $wpdb->query($ss);// or die(mysql_error());

        // send email to admin (again -- request might have delayed at beyonic api)
        PricerrTheme_send_email_when_withdraw_completed($row->uid, $row->methods, PricerrTheme_get_show_price($row->amount));

        $reason = sprintf(__('Mobile Withdraw to %s - Details: %s', 'pricerrtheme'), $row->methods, $row->payeremail);

        // log to history again
        PricerrTheme_add_history_log('0', $reason, $row->amount, $row->uid);
        wp_redirect(get_site_url()); // redirect to any appropriate page
        exit;
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
                echo '<div class="saved_thing"><div class="padd10">' . __('Please Set Api Key In Payment Gateways under beyonic tab!', 'pricerrtheme') . '</div></div>';
                die();
            }
            $phoneNo = $row->payeremail; // TODO add phone No validation or extraction from passed string
            $amount = $row->amount;
            $currency = 'BXC'; // PricerrTheme_get_currency(); // TODO replace currency
            $callback_url = get_site_url() . "/?beyonic_response_withdraw_request=" . $row_id; // url that will receive payment response
            Beyonic::setApiKey($api_key);

            $payment = Beyonic_Payment::create(array(
                "phonenumber" => $phoneNo,
                "first_name" => "John", // include if they were captured on the form
                "last_name" => "Doe",
                "amount" => $amount,
                "currency" => $currency,
                "description" => "payment",
                "payment_type" => "money",
                "callback_url" => $callback_url,
                "metadata" => array("id" => $row_id)
            ));

            $error_check = (is_array($payment) && !empty($payment)) ? $payment['phone_nos']['state'] : 'error';
            if ($error_check == 'error') {
                // payment failed, update database(mark as unresolved again) and print error to screen
                $last_error = $payment['phone_nos']['last_error'];
                echo '<div class="saved_thing"><div class="padd10">' . __('Payment Failed!, ' . $last_error, 'pricerrtheme') . '</div></div>';
                $ss = "update " . $wpdb->prefix . "job_withdraw set done='0', rejected_on='0', rejected='0', datedone='0' where id='$row_id'";
                $wpdb->query($ss);
            }
        }
    }

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
        update_option('PricerrTheme_beyonic_api_key', $PricerrTheme_beyonic_api_key);
        update_option('PricerrTheme_beyonic_enable', $PricerrTheme_beyonic_enable);
        update_option('PricerrTheme_beyonic_withdraw_fee', $PricerrTheme_beyonic_withdraw_fee);

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
