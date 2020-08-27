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
//link to woocommerce settings
function pricetheme_beyonic_settings_link($links)
{
    $link = admin_url('admin.php?page=payment-methods#tabs_beyonic');
    $links[] = '<a href="' . $link . '">Payment Settings</a>';
    return $links;
}

$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'pricetheme_beyonic_settings_link');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'lib/Beyonic.php'; // import beyonic php lib
include 'lib/Flash2.php'; // import lib for showing flash messages
/** returns database connection object
 * @return wpdb
 */
function db()
{
    global $wpdb; // link to wordpress database object;
    return $wpdb;
}

/** displays flash messages tp screen
 * @param $msg
 * @param bool $info // true for info , false for error
 * @param null $show // display messages or return them, 1 to show them, 2 to return them
 * @return void | string
 */
function showMsg($msg, $info = false, $show = null)
{
    // $flash = (new Flash);
    if ($show === 1) {
        // $flash->show();
        Flash2::show();
        return;
    }
    if ($show === 2) {
        return Flash2::getAll(); // $flash->getAll();
    }
    if (is_admin()) {
        Flash2::add($info ? 'info' : 'error', $msg);
        // $flash->add($info ? 'info' : 'error', $msg);
    } else {
        Flash2::add($info ? 'fInfo' : 'fError', $msg);
        // $flash->add($info ? 'fInfo' : 'fError', $msg);
    }
}

ini_set('display_errors', '0');
add_filter('PricerrTheme_payment_methods_tabs', 'PricerrTheme_add_new_beyonic_tab');
add_filter('PricerrTheme_payment_methods_action', 'PricerrTheme_add_new_beyonic_pst');
add_filter('PricerrTheme_payment_methods_content_divs', 'PricerrTheme_add_new_beyonic_cnt');
add_filter('admin_init', 'beyonic_pricerr_temp_redir');
// check if request was closed by client
add_filter('init', 'closeRequest');
add_filter('PricerrTheme_withdraw_method', 'PricerrTheme_withdraw_method_beyonic');
// add withdraw form
add_action('PricerrTheme_payments_withdraw_options', 'PricerrTheme_payments_withdraw_beyonic');
// hook into deposit function
add_action('pricerrtheme_deposit_payment_gateways', 'PricerrTheme_payments_deposit_beyonic');
//configure rest for this plugin
remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
add_filter('rest_pre_serve_request', static function ($value) {
    $origin = 'https://app.beyonic.com';
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT');
    header('Access-Control-Allow-Credentials: true');
    return $value;
});
// limit no of posts per page to 1
add_filter('rest_query_var-posts_per_page', static function ($posts_per_page) {
    if (1 < (int)$posts_per_page) {
        $posts_per_page = 1;
    }
    return $posts_per_page;
});

// register routes after initialisation of wp
add_action('init', 'registerRoutes');
function registerRoutes()
{
    // register custom routes to handle payment requests
    register_rest_route('beyonic-api', '/payments', array(
        'methods' => WP_REST_Server::ALLMETHODS,
        'callback' => 'handlePayment',
        'permission_callback' => 'checkBeyonicPermissions',
    ));
    // register custom routes to handle collection requests
    register_rest_route('beyonic-api', '/collections', array(
        'methods' => WP_REST_Server::ALLMETHODS,
        'callback' => 'handleCollection',
        'permission_callback' => 'checkBeyonicPermissions',
    ));
}

// register javascript to handle ajax requests on backend
add_action('admin_enqueue_scripts', 'addAdminJs');
function addAdminJs()
{
    // only enqueue script when viewing withdraw requests
    $page = $_GET['page'];
    if ($page !== 'withdraw-req') {
        return;
    }
    // Enqueue javascript
    wp_enqueue_script('beyonic-admin', plugin_dir_url(__FILE__) . 'lib/beyonic-admin.js', array('jquery'));

    // The wp_localize_script allows us to output the ajax_url path for our script to use.
    wp_localize_script('beyonic-admin', 'beyonicObj',
        array('ajaxUrl' => admin_url('admin-ajax.php'), 'action' => 'adminAjax', 'nonce' => wp_create_nonce('beyonic-admin-nonce'))
    );

}

// register javascript to handle ajax requests on front end
add_action('wp_enqueue_scripts', 'addClientCss');
function addClientCss()
{
    // add css to style buttons and other parts
    wp_enqueue_style('beyonic-client-css', plugin_dir_url(__FILE__) . 'lib/beyonic-client.css');
}

// register javascript to handle ajax requests on front end
add_action('wp_enqueue_scripts', 'addClientJs');
function addClientJs()
{
    // only enqueue script when viewing deposit page
    $page = $_GET['pg'];
    if ($page === 'deposit') {
        wp_enqueue_script('beyonic-client', plugin_dir_url(__FILE__) . 'lib/beyonic-client.js', array('jquery'));
        // array with params that are passed to client side js
        $arr = array('ajaxUrl' => admin_url('admin-ajax.php'), 'action' => 'clientAjax', 'nonce' => wp_create_nonce('beyonic-client-nonce'));
        if (isset($_POST['depositAmount'], $_POST['depositPhoneNo'])) {
            // if client submitted deposit request, pass post body(with user id) to javascript
            // this will be used to identify request from beyonic
            $arr = array_merge($arr, $_POST);
            $u = wp_get_current_user(); // get logged in  user_id
            if ($u) {
                $arr = array_merge($arr, ['uid' => $u->ID]);
            }
        }
        // pass above array for script to use as beyonicObj.
        wp_localize_script('beyonic-client', 'beyonicObj', $arr);
    }

}

/**
 *function that handles ajax requests from the frontend
 */
function clientAjax()
{
    $nonce = $_REQUEST['nonce'];
    // check security token
    if (!wp_verify_nonce($nonce, 'beyonic-client-nonce')) {
        die('Access Denied !');
    }
    // get params passed by javascript
    $depositAmount = $_REQUEST['depositAmount'];
    $depositPhoneNo = $_REQUEST['depositPhoneNo'];
    $results = -1;
    if (isset($depositAmount, $depositPhoneNo)) {
        $uid = $_REQUEST['uid'];
        $key = getClientCacheKey($depositAmount, $depositPhoneNo, false, $uid);
        $check = cacheRequest($key);
        if (is_numeric($check)) { // either 0, 1, 2
            $results = $check;
            $colId = getClientCacheKey($depositAmount, $depositPhoneNo, true, $uid);
            if (($check === 1) && $colId) {
                // delete transients
                deleteClientCacheKeys($depositAmount, $depositPhoneNo, $uid);
            }
        }
    }
    wp_send_json($results, 200);
}

// register function as ajax handler format wp_ajax_func_name
add_action('wp_ajax_clientAjax', 'clientAjax');

/**
 *function that handles ajax requests from the admin panel
 */
function adminAjax()
{
    $nonce = $_REQUEST['nonce'];

    // check security token
    if (!wp_verify_nonce($nonce, 'beyonic-admin-nonce')) {
        die('Access Denied !');
    }
    $ids = $_REQUEST['ids']; // ids passed by javascript from withdraws table
    $results = [];
    if (is_array($ids)) {
        foreach ($ids as $row_id) {
            $key = getAdminCacheKey(((int)$row_id));
            $check = cacheRequest($key);
            if (is_numeric($check)) { // either 0, 1, 2
                $results[$row_id] = $check;
                // payment completed clear payment metadata
                if ($check === 1) {
                    deleteAdminCacheKeys($row_id);
                }
            }
        }
    }
    wp_send_json($results, 200);
}

// register function as ajax handler format wp_ajax_func_name
add_action('wp_ajax_adminAjax', 'adminAjax');

// add email config to send client error messages to admin
// add_action('phpmailer_init', 'send_errors');

/**configure mailer
 * @param $phpmailer
 */
/*function send_errors($phpmailer)
{
    $config = ['host' => 'mail.kyeeyo.com', 'auth' => true, 'port' => 465, 'secure' => 'tls', 'username' => 'info@kyeeyo.com', 'password' => 'C@ouJxK~*U$+', 'from' => 'info@kyeeyo.com', 'fromName' => 'Kyeeyo Ug'];
    $phpmailer->isSMTP();
    $phpmailer->Host = $config['host'];
    $phpmailer->SMTPAuth = $config['auth'];
    $phpmailer->Port = $config['port'];
    $phpmailer->SMTPSecure = $config['secure'];
    $phpmailer->Username = $config['username'];
    $phpmailer->Password = $config['password'];
    $phpmailer->From = $config['from'];
    $phpmailer->FromName = $config['fromName'];
}*/

/** send client side error messages to admin
 * @param $msg
 */
function logError($msg)
{
    $time = date("F jS Y, H:i", time() + 25200);
    $user = null;
    if (function_exists('wp_get_current_user')) {
        $user = wp_get_current_user();
    }
    if ($user) {
        $time .= ' USERID: ' . $user->ID . ' ';
    }
    $file = plugin_dir_path(__FILE__) . '/errors.txt';
    $open = fopen($file, "a");
    fwrite($open, $time . '  :  ' . json_encode($msg) . "\r\n");
    fclose($open);
}

/** caches to options table, used with ajax to keep track of status of beyonic requests
 * @param $key //name of transient
 * @param null $value // value to set to transient
 * @param false $delete // delete transient after use
 * @return number|string|boolean
 */
function cacheRequest($key, $value = null, $delete = false)
{
    if ($delete) {
        return delete_option($key);
    }
    $opt = get_option($key);
    if ($value !== null) {
        if ($opt !== null) {
            return update_option($key, $value);
        }
        return add_option($key, $value);
    }
    return $opt;
}

/** returns cache key, for consistency when using cache function
 * @param $row_id
 * @param bool $id // used to track old Requests, when true, it returns request_id, otherwise it returns status
 * @return string
 */
function getAdminCacheKey($row_id, $id = false)
{
    if ($id) {
        return 'BeyonicRequest_Id_' . $row_id;
    }
    return 'BeyonicRequest_status_' . $row_id;
}

/** converts array from beyonic api post request to object
 * @param $array
 * @return stdClass
 */
function arrayToObject($array)
{
    $object = new stdClass();
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $object->$key = arrayToObject($value);
        } else {
            $object->$key = $value;
        }
    }
    return $object;
}


/**
 * process beyonic payment and updates database
 * @param WP_REST_Request $request Full details about the request
 *
 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
 */
function handlePayment($request)
{
    $response = rest_ensure_response(new WP_REST_Response('Ok !', 200));

    $body = $request->get_body_params();
    if (!isset($body)) { // empty body
        $response->set_status(404);
        $response->set_data('Smell You Later !');
        return $response;
    }
    if (is_string($body)) { // json body
        $body = json_decode($body, false); // change to object
    }
    if (is_array($body)) { // change to object
        $body = arrayToObject($body);
    }

    $row_id = $body->data->metadata->id; // TODO not working in production, send body to my email and debug
    if (is_numeric($row_id)) { // response from beyonic through webhook api
        $payment = $body->data;
        $goOn = checkPayment($payment, $row_id, true);
        if ($goOn === 1) {
            // payment was successful, update database(set withdraw request as resolved)

            $s = "select * from " . db()->prefix . "job_withdraw where id='$row_id'";
            $row = db()->get_results($s);
            if (isset($row)) {
                $row = $row[0];
                $tm = current_time('timestamp', 0);
                $ss = "update " . db()->prefix . "job_withdraw set done='1', datedone='$tm' where id='$row_id'";
                db()->query($ss);

                // send email to admin (again -- request might have delayed at beyonic api)
                PricerrTheme_send_email_when_withdraw_completed($row->uid, $row->methods, PricerrTheme_get_show_price($row->amount));

                $reason = sprintf(__('Mobile Withdraw to %s - Details: %s', 'pricerrtheme'), $row->methods, $row->payeremail);

                // log to history again
                PricerrTheme_add_history_log('0', $reason, $row->amount, $row->uid);
            }
        }
        // update request status for use by ajax query later
        cacheRequest(getAdminCacheKey($row_id), $goOn);
    }
    $response->set_data('Wakanda Forever !');
    return $response;
}

/** process beyonic collection and updates database
 * @param WP_REST_Request $request Full details about the request
 *
 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
 */
function handleCollection($request)
{
    $response = rest_ensure_response(new WP_REST_Response('Ok !', 200));

    $body = $request->get_body_params();
    if (!isset($body)) { // empty body
        $response->set_status(404);
        $response->set_data('Smell You Later !');
        return $response;
    }
    if (is_string($body)) { // json body
        $body = json_decode($body, false); // change to object
    }
    if (is_array($body)) { // change to object
        $body = arrayToObject($body);
    }
    $collection = $body->data;
    $goOn = checkCollection($collection, true);
    if ($goOn === 1) { // TODO delete transients of failed requests, if they weren't reset
        // payment was successful, update user credits
        updateClientCredits($collection->amount, $collection->phonenumber, $collection->id, $collection->metadata->uid);
    }
    // update request status for use by ajax query later
    cacheRequest(getClientCacheKey($collection->amount, $collection->phonenumber, false, $collection->metadata->uid), $goOn);
    $response->set_data('Wakanda Forever !');
    return $response;
}


/** process authorization header and return true or false
 * @param $request WP_REST_Request
 * @return boolean
 */
function checkBeyonicPermissions($request)
{
    // TODO complete this
    $auth = $request->get_header('authorization');
    // username set in beyonic account
    // password set in beyonic account
    return true;
}

function getClientCacheKey($amount, $phoneNo, $id = false, $uid = null)
{
    if (!$uid) {
        $user = wp_get_current_user();
        if (!isset($user)) {
            return null;
        }
        $uid = $user->ID;
    }
    if ($uid === 0) { // no logged in user
        return null;
    }
    $key = ((int)$amount) . '_' . $phoneNo . '_' . $uid;
    return getAdminCacheKey($key, $id);
}

function makeRequest($amount, $phoneNo, $retry = false)
{
    /*try {*/
    $api_key = get_option('PricerrTheme_beyonic_api_key');//api key set in admin tab of beyonic
    if (empty($api_key)) { // api key not set, so throw error
        showMsg(__('Account Config Error, Contact Admin!', 'pricerrtheme'));
        logError('Api Key is not set');
        return null;
    }
    Beyonic::setApiKey($api_key);
    $account_fname = null;
    $account_lname = null;
    $user = wp_get_current_user();
    if (!isset($user)) {
        showMsg(__('Please Login And try Again!', 'pricerrtheme'));
        logError('Api Key is not set');
        return null;
    }
    $account_fname = $user->display_name;
    $account_lname = $user->user_nicename;
    $phoneNo = validatePhoneNo($phoneNo);
    if (!$phoneNo) {
        showMsg(__('Phone No invalid or Not Supported !', 'pricerrtheme'));
        logError('Client Entered An Invalid Phone No Client: ' . $account_fname . ' ' . $account_lname);
        return null;
    }
    $currency = PricerrTheme_get_currency();
    if (!$currency) {
        showMsg(__('Currency Error !', 'pricerrtheme'));
        logError('Currency Not Configured');
        return null;
    }
    $callback_url = 'https://kyeeyo.com/wp-json/beyonic-api/collections';// get_site_url() . '/wp_json/beyonic-api/collections';
    $hooks = checkWebHooks($callback_url, 'collectionrequest.status.changed');
    if ($hooks) { // webhook exits
        $callback_url = null;
    }
    $account = null;
    $account_id = get_option('PricerrTheme_beyonic_account_Id');//account id set in admin tab of beyonic
    if (is_numeric($account_id)) {
        $acc = Beyonic_Account::get((int)$account_id);
        if ($acc->id === null) {
            showMsg(__('Account Error, contact Admin !', 'pricerrtheme'));
            logError('Beyonic Account Id is not set');
            return null;
        }
        if (strtolower($acc->status) !== 'active') {
            showMsg(__('Account is Inactive contact Admin !', 'pricerrtheme'));
            logError('Account Inactive !');
            return null;
        }
        // check currency
        if ($acc->currency !== $currency) {
            showMsg(__('Currency Miss Match !', 'pricerrtheme'));
            logError('Currency Miss Match !');
            return null;
        }
        $account = $acc;
    }
    $beyonicData = array(
        "phonenumber" => $phoneNo,
        "first_name" => $account_fname, // include if they were captured on the form
        "last_name" => $account_lname,
        "amount" => $amount,
        "currency" => $currency,
        "reason" => "Kyeeyo Ug Top Up",
        "send_instructions" => true,
        'retry_interval_minutes' => null,
        'expiry_date' => '3 minutes',
        'max_attempts' => 0,
        "metadata" => array("uid" => $user->ID)
    );
    if (!isset($beyonicData['first_name'])) {
        $beyonicData['first_name'] = 'Kyeeyo';
    }
    if (!isset($beyonicData['last_name'])) {
        $beyonicData['last_name'] = 'user';
    }
    if ($callback_url) {
        $beyonicData = array_merge($beyonicData, ['callback_url' => $callback_url]);
    }

    if ($account) {
        $beyonicData = array_merge($beyonicData, ['account' => $account->id]);
    } else {
        showMsg(__('Account Not Configured !', 'pricerrtheme'));
        logError('Account is Null or Not Configured !');
        return null;
    }
    $collection = null;
    // check for old collections
    // check cache for old collection id
    $cache_key = getClientCacheKey($amount, $phoneNo, true);
    $old_id = cacheRequest($cache_key);
    if (is_numeric($old_id)) {
        $collection = Beyonic_Collection_Request::get($old_id);
    } else {
        // get old collection from Api
        // get one collection to estimate total records
        $counter = Beyonic_Collection_Request::getAll(array('limit' => 1, 'offset' => 0, 'phonenumber' => $beyonicData['phonenumber'], 'amount' => $beyonicData['amount'], 'currency' => $beyonicData['currency']));
        // get all records matching above criteria
        $oldCollections = Beyonic_Collection_Request::getAll(array('limit' => $counter['count'], 'offset' => 0, 'phonenumber' => $beyonicData['phonenumber'], 'amount' => $beyonicData['amount'], 'currency' => $beyonicData['currency']));
        if (count($oldCollections['results']) > 0) {
            // filter out collections with user id
            $filtered = array_filter($oldCollections['results'], static function ($p) use ($user) {
                return $p->metadata->uid === $user->ID;
            });
            uasort($filtered, 'compare'); // sort according to collection id
            $keys = array_keys($filtered); // get original array indices
            $key = $keys[count($keys) - 1]; // extract last key which is the latest
            $collection = $oldCollections['results'][$key];
            cacheRequest($cache_key, $collection->id); // write id to cache to prevent future computations
        }
    }
    if ($collection && !$retry) {
        return checkCollection($collection);
    }
    $collection = Beyonic_Collection_Request::create($beyonicData);

    cacheRequest($cache_key, $collection->id); // write id to cache to prevent future computations
    return checkCollection($collection);
    /*} catch (Exception $ex) {
        logError($ex->getTraceAsString());
        showMsg(__('Your Request Cannot be Completed At this Time !', 'pricerrtheme'));
        return null;
    }*/
}

function updateClientCredits($amount, $phoneNo, $colId, $uid = null)
{
    $user = null;
    if (!$uid) {
        $user = wp_get_current_user();
        $uid = $user->ID;
    } else {
        $user = get_userdata($uid);
    }
    $reason = sprintf(__('Payment Received From Beyonic Ref No %s  Phone No %s', 'pricerrtheme'), $colId, $phoneNo);
    // check log to see if record exits and act accordingly
    $post = db()->get_row("SELECT * FROM " . db()->prefix . "job_payment_transactions WHERE amount='$amount' and uid='$uid' and reason='$reason'");
    if ($post) {
        return;
    }

    if ($user) {
        $cr = PricerrTheme_get_credits($uid);
        PricerrTheme_update_credits($uid, $cr + $amount);
        // log request
        PricerrTheme_add_history_log('1', $reason, $amount, $uid);
        $message = sprintf(__('Dear %s, Mobile Payment Has Been Received Amount %s, From %s Thank you !', 'pricerrtheme'), $user->user_nicename, $amount, $phoneNo);
        PricerrTheme_send_email($user->user_email, 'MOBILE DEPOSIT To KYEEYO', $message);
    }
}

/**
 * function that generates a form to capture user details, phone number, amount while adding money to the e-wallet
 */
function PricerrTheme_payments_deposit_beyonic()
{
    $PricerrTheme_beyonic_enable = get_option('PricerrTheme_beyonic_enable');
    if ($PricerrTheme_beyonic_enable == "yes"):
        if (isset($_POST['retryDeposit'])) { // deposit failed and retry button has been clicked, this must be checked first
            $_POST['depositBeyonic'] = 1; // mark as true so that statement below is executed
            $_POST['depositError'] = null; // reset errors
        }
        if (isset($_POST['depositBeyonic'])) {
            $_POST['completeDeposit'] = 1; // mark as true so that statement below is executed
            $_POST['retryDeposit'] = 1; // mark as a new request so that old requests are not checked
        }
        $uid = wp_get_current_user()->ID; // get logged in  user_id
        if (isset($_POST['completeDeposit'])) {
            $req = makeRequest($_POST['depositAmount'], $_POST['depositPhoneNo'], isset($_POST['retryDeposit'])); // returns 0, 1 , 2
            if ($req === 0) {
                // request not yet complete
                $_POST['PricerrTheme_beyonic_deposit'] = 1;
                $_POST['retryDeposit'] = null;
            }
            if ($req === 1) {
                // request complete
                $_POST['finishDeposit'] = 1;
                $_POST['retryDeposit'] = null;

                $req_id = cacheRequest(getClientCacheKey($_POST['depositAmount'], $_POST['depositPhoneNo'], true, $uid));
                // update user balance
                updateClientCredits($_POST['depositAmount'], $_POST['depositPhoneNo'], $req_id, $uid);
                // delete request metadata
                deleteClientCacheKeys($_POST['depositAmount'], $_POST['depositPhoneNo'], $uid);
            }
            if ($req === 2 || $req === null) {
                // request failed
                $_POST['retryDeposit'] = 1;
                $_POST['finishDeposit'] = null;
                $_POST['PricerrTheme_beyonic_deposit'] = null;
                $_POST['depositError'] = showMsg(null, null, 2);
            }
        }
        if (isset($_POST['resetDeposit'])) {
            deleteClientCacheKeys($_POST['depositAmount'], $_POST['depositPhoneNo'], $uid);
            $_POST['PricerrTheme_beyonic_deposit'] = null;
            $_POST['finishDeposit'] = null;
            $_POST['retryDeposit'] = null;
            $_POST['depositAmount'] = null;
            $_POST['depositPhoneNo'] = null;
            $_POST['depositError'] = null;
        }
        ?>
        <br/><br/>
        <div class="box_title3 mt-4 mb-4">
            <div class="inner-me"><?php _e('Transfer From Mobile Money', 'pricerrtheme') ?></div>
        </div>
        <?php if (isset($_POST['finishDeposit'])): ?>
        <table class="skf">
            <form method="post" enctype="application/x-www-form-urlencoded" id="retryForm">
                <tr>
                    <td colspan="3" style="padding:16px;">
                        Amount <strong><?php echo $_POST['depositAmount'] ?></strong> Has Been Credited To Your
                        Wallet
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="submit" name="resetDeposit"
                               class="btn btn-danger red-button-me"
                               value="reset"/></td>
                </tr>
            </form>
        </table>
    <?php elseif (isset($_POST['PricerrTheme_beyonic_deposit'])): ?>
        <table class="skf">
            <form method="post" enctype="application/x-www-form-urlencoded" id="completeForm">
                <tr>
                    <td colspan="3" style="padding:16px;">
                        A request has been sent to your phone
                        <strong><?php echo $_POST['depositPhoneNo'] ?></strong>, Enter
                        Your PIN to approve and click complete below (Expires in 3 minutes)
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="submit" name="completeDeposit" id="completeFormBtn"
                               class="btn btn-primary green-button-me"
                               value="complete"/></td>
                    <td></td>
                    <td>
                        <input type="submit" name="resetDeposit"
                               class="btn btn-danger red-button-me"
                               value="reset"/></td>
                </tr>
                <!-- recapture previous form values-->
                <input value="<?php echo $_POST['depositAmount']; ?>" contentEditable="false" name="depositAmount"
                       hidden required/>
                <input value="<?php echo $_POST['depositPhoneNo']; ?>" contentEditable="false" hidden
                       name="depositPhoneNo" required/>
                <input value="<?php echo $_POST['tm_tm']; ?>" contentEditable="false" hidden name="tm_tm" required/>
                <input value="<?php echo $_POST['PricerrTheme_beyonic_deposit']; ?>" contentEditable="false" hidden
                       name="PricerrTheme_beyonic_deposit"/>
            </form>
        </table>
    <?php elseif (isset($_POST['depositError'])): ?>
        <table class="skf">
            <form method="post" enctype="application/x-www-form-urlencoded" id="depositErrorForm">
                <tr>
                    <td colspan="3" style="padding:16px;">
                        <?php echo $_POST['depositError'] ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="submit" name="retryDeposit" id="depositErrorFormBtn"
                               class="btn btn-primary green-button-me"
                               value="Retry"/></td>
                    <td></td>
                    <td>
                        <input type="submit" name="resetDeposit"
                               class="btn btn-danger red-button-me"
                               value="reset"/></td>
                </tr>
                <!-- recapture previous form values-->
                <input value="<?php echo $_POST['depositAmount']; ?>" contentEditable="false" name="depositAmount"
                       hidden required/>
                <input value="<?php echo $_POST['depositPhoneNo']; ?>" contentEditable="false" hidden
                       name="depositPhoneNo" required/>
                <input value="<?php echo $_POST['tm_tm']; ?>" contentEditable="false" hidden name="tm_tm" required/>
                <input value="<?php echo $_POST['PricerrTheme_beyonic_deposit']; ?>" contentEditable="false" hidden
                       name="PricerrTheme_beyonic_deposit"/>
            </form>
        </table>
    <?php else: ?>
        <table class="skf">
            <form method="post" enctype="application/x-www-form-urlencoded" id="depositForm">
                <input type="hidden" value="<?php echo current_time('timestamp', 0) ?>"
                       name="tm_tm"/>
                <tr>
                    <!-- added this input to help identify beyonic payment when checking for deposit(beyonic_pricerr_temp_redir())--></td>
                    <td><input value="1" type="number" contentEditable="false" hidden
                               name="PricerrTheme_beyonic_deposit"/></td>
                </tr>
                <tr>
                    <td><?php _e('Deposit amount:', 'pricerrtheme'); ?></td>
                    <td>
                        <input value="<?php echo isset($_POST['PricerrTheme_beyonic_deposit']) ? $_POST['depositAmount'] : ''; ?>"
                               type="number" size="10" min="1" name="depositAmount"
                               required/> <?php echo PricerrTheme_get_currency(); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Phone Number (in international format e.g +256701123456):', 'pricerrtheme'); ?></td>
                    <td>
                        <input value="<?php echo isset($_POST['PricerrTheme_beyonic_deposit']) ? $_POST['depositPhoneNo'] : ''; ?>"
                               type="text" maxlength="14" minlength="10"
                               pattern="[+]{1}[0-9]{3}[0-9]{9}"
                               name="depositPhoneNo" required/></td>
                </tr>
                </tr>

                <tr>
                    <td></td>
                    <td>
                        <input type="submit" name="depositBeyonic" id="depositFormBtn"
                               class="btn btn-primary green-button-me"
                               value="<?php _e('Deposit Now', 'pricerrtheme'); ?>"/></td>
                </tr>
            </form>
        </table>
    <?php endif; ?>
    <?php endif;
}

/**
 * @param $collection // collection object from beyonic
 * @param bool $silent // show errors to front end, false for rest
 * @return int // 0, 1 ,2
 */
function checkCollection($collection, $silent = false)
{
    $cache_key = getClientCacheKey($collection->amount, $collection->phonenumber, false, $collection->metadata->uid);
    switch (strtolower($collection->status)) {
        case 'new':
        case 'pending':
        case 'instructions_sent':
            if (!$silent) {
                showMsg(__('Request is Waiting your Approval', 'pricerrtheme'), true);
            }
            cacheRequest($cache_key, 0);
            return 0;
        case 'successful':
            if (!$silent) {
                showMsg(__('Request Completed Successfully', 'pricerrtheme'), true);
            }
            cacheRequest($cache_key, 1);
            return 1;
        case 'reversed':
        case 'failed':
            if (!$silent) {
                showMsg(__('Request Failed!, Reason:- ' . $collection->error_message, 'pricerrtheme'));
            }
            break;
        case 'expired':
            if (!$silent) {
                showMsg(__('Request Expired', 'pricerrtheme'));
            }
            break;
        default:
            if (!$silent) {
                showMsg(__('Request Cannot be processed, try again later' . $collection->status, 'pricerrtheme'));
            }
            break;
    }
    cacheRequest($cache_key, 2);
    return 2;
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
    // TODO check for orphaned transients and remove them
    if (isset($_GET['page']) && $_GET['page'] == 'withdraw-req') {
        showMsg(null, null, 1);
    }
    // payment was accepted from withdraw requests options, make payment request to beyonic api
    if (isset($_GET['page'], $_GET['tid']) && $_GET['page'] == 'withdraw-req') {
        $row_id = $_GET['tid'];
        // read database with passed row id
        $s = "select * from " . db()->prefix . "job_withdraw where id='$row_id'";
        $row = db()->get_results($s);
        $row = $row[0];
        $method = $row->methods;
        if ($row->done == 0 && $method == 'Beyonic') {
            $api_key = get_option('PricerrTheme_beyonic_api_key');//api key set in admin tab of beyonic
            if (empty($api_key)) { // api key not set, so throw error
                showMsg(__('Please Set Api Key In Payment Gateways under beyonic tab!', 'pricerrtheme'));
                wp_redirect(wp_get_referer());
                die();
            }
            Beyonic::setApiKey($api_key);
            $payment = null;

            $withdrawFee = get_option('PricerrTheme_beyonic_withdraw_fee');
            if (empty($withdrawFee)) {
                $withdrawFee = 0; // withdraw fee not set in options config, default it to zero
            }
            $amount = $row->amount - $withdrawFee;
            $currency = PricerrTheme_get_currency();
            // check for old payments
            // check cache for old payment id
            $cached_payment_id = cacheRequest(getAdminCacheKey($row_id, true));

            if (is_numeric($cached_payment_id)) {
                $payment = Beyonic_Payment::get($cached_payment_id);
            } else {
                // get old payment from Api
                // get one payment to estimate total records
                $counter = Beyonic_Payment::getAll(array('limit' => 1, 'offset' => 0, 'amount' => $amount, 'currency' => $currency));
                // get all records matching above criteria
                $oldPayments = Beyonic_Payment::getAll(array('limit' => $counter['count'], 'offset' => 0, 'amount' => $amount, 'currency' => $currency));
                if (count($oldPayments['results']) > 0) {
                    // filter out payments with passed row id
                    $filtered = array_filter($oldPayments['results'], static function ($p) use ($row_id) {
                        return $p->metadata->id === $row_id;
                    });
                    uasort($filtered, 'compare'); // sort according to payment id
                    $keys = array_keys($filtered); // get original array indices
                    $key = $keys[count($keys) - 1]; // extract last key which is the latest
                    $payment = $oldPayments['results'][$key];
                    cacheRequest(getAdminCacheKey($row_id, true), $payment->id);// write id to cache to prevent future computations
                }
            }

            if ($payment) {
                showMsg(__('Payment Already Queued !', 'pricerrtheme'), true);
            } else {
                $account_id = get_option('PricerrTheme_beyonic_account_Id');//account id set in admin tab of beyonic
                $account_fname = null; // TODO get from database from payeremail field
                $account_lname = null;
                $user = getBeyonicUser($row->uid);
                if (!isset($user)) {
                    $account_fname = 'Kyeeyo'; // john
                    $account_lname = 'user'; // doe
                } else {
                    $account_fname = $user->display_name;
                    $account_lname = $user->user_nicename;
                }
                if (!isset($row->payeremail)) {
                    showMsg(__('Missing Phone No', 'pricerrtheme'));
                    wp_redirect(wp_get_referer());
                    die();
                }
                $phoneNo = validatePhoneNo($row->payeremail);
                if (!$phoneNo) {
                    showMsg(__('Phone No invalid or Not Supported !', 'pricerrtheme'));
                    wp_redirect(wp_get_referer());
                    die();
                }

                if ($amount === 0) {
                    showMsg(__('Amount minus withdraw Fee is Zero', 'pricerrtheme'));
                    wp_redirect(wp_get_referer());
                    die();
                }

                $account = null;
                if (is_numeric($account_id)) {
                    $acc = Beyonic_Account::get((int)$account_id);
                    if ($acc->id === null) {
                        showMsg(__('Wrong Account Id, Please get the correct Account Id from Your Beyonic Account', 'pricerrtheme'));
                        wp_redirect(wp_get_referer());
                        die();
                    }
                    if (strtolower($acc->status) !== 'active') {
                        showMsg(__('Account is Inactive contact Admin !', 'pricerrtheme'));
                        logError('Account Inactive !');
                        return null;
                    }
                    // check currency
                    if ($acc->currency !== $currency) {
                        showMsg(__('Currency Miss Match !', 'pricerrtheme'));
                        wp_redirect(wp_get_referer());
                        die();
                    }
                    $account = $acc;
                }
                $callback_url = 'https://kyeeyo.com/wp-json/beyonic-api/payments';// get_site_url() . '/wp_json/beyonic-api/payments';
                $hooks = checkWebHooks($callback_url, 'payment.status.changed');
                if ($hooks) { // webhook exits
                    $callback_url = null;
                }
                $beyonicData = array(
                    "phonenumber" => $phoneNo,
                    "first_name" => $account_fname, // include if they were captured on the form
                    "last_name" => $account_lname,
                    "amount" => $amount,
                    "currency" => $currency,
                    "description" => "payment", //  .' timestamp '. current_time('timestamp'), // add timestamp to prevent duplicates
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
                    $beyonicData = array_merge($beyonicData, ['callback_url' => $callback_url]);
                }

                if ($account) {
                    if ($account->balance < $beyonicData['amount']) {
                        showMsg(__('Insufficient Account Balance, Please Add funds To the Account and try Again', 'pricerrtheme'));
                        wp_redirect(wp_get_referer());
                        die();
                    }
                    $beyonicData = array_merge($beyonicData, ['account' => $account->id]);
                } else {
                    showMsg(__('Account is Null or Not Configured !', 'pricerrtheme'));
                    wp_redirect(wp_get_referer());
                    die();
                }

                $payment = Beyonic_Payment::create($beyonicData);
                cacheRequest(getAdminCacheKey($row_id, true), $payment->id);// write id to cache to prevent future computations
            }
            $goOn = checkPayment($payment, $row_id);
            cacheRequest(getAdminCacheKey($row_id), $goOn); // store status for ajax query
            if ($goOn === 1) { // make complete was clicked again
                // payment complete, delete transients
                deleteAdminCacheKeys($row_id);
            }
            wp_redirect(wp_get_referer());
            die();
        }
        if ($method == 'Beyonic') { // remove any old transients
            deleteAdminCacheKeys($row_id);
        }

    }
    if (isset($_GET['page'], $_GET['den_id']) && $_GET['page'] == 'withdraw-req') {
        // request was denied, delete transients
        $row_id = $_GET['den_id'];
        deleteAdminCacheKeys($row_id);
    }

}

/**
 *Hooks into the init request and checks if client closed request then deletes request metadata
 */
function closeRequest()
{
    if (isset($_GET['pg'], $_GET['id']) && $_GET['pg'] == 'closewithdrawal') {
        // request was closed by user, delete transients
        deleteAdminCacheKeys($_GET['id']);
    }
}

function deleteAdminCacheKeys($row_id)
{
    cacheRequest(getAdminCacheKey($row_id, true), null, true); // for id
    cacheRequest(getAdminCacheKey($row_id), null, true); // for status
}

function deleteClientCacheKeys($amount, $phoneNo, $uid = null)
{
    cacheRequest(getClientCacheKey($amount, $phoneNo, true, $uid), null, true);
    cacheRequest(getClientCacheKey($amount, $phoneNo, false, $uid), null, true);
}

function checkWebHooks($url, $event)
{
    // get one hook to estimate total records
    $counter = Beyonic_Webhook::getAll(array('limit' => 1, 'offset' => 0, 'event' => $event));
    // get all records matching above criteria
    $hooks = Beyonic_Webhook::getAll(array('limit' => $counter['count'], 'offset' => 0, 'event' => $event));

    if (count($hooks['results']) > 0) {
        // filter out hooks with passed row url
        $filtered = array_filter($hooks['results'], static function ($p) use ($url) {
            return $p->target === $url || $p->target === 'https://api.kyeeyo.com';
        });
        return count($filtered) > 0; // webhook exits
    }
    return false;
}

/**
 * @param $payment // payment object from beyonic
 * @param $row_id // row id of job request
 * @param bool $silent // write to DB and inform user or return result silently
 * @return int // 0, 1, 2
 */
function checkPayment($payment, $row_id, $silent = false)
{
    switch (strtolower($payment->state)) {
        case 'scheduled':
        case 'new':
            if (!$silent) {
                showMsg(__('Payment Has Been Queued! and Is Waiting Approval', 'pricerrtheme'), true);
            }
            return 0;
        case 'processed':
        case 'complete':
            if (!$silent) {
                showMsg(__('Payment Completed Successfully', 'pricerrtheme'), true);
                PricerrTheme_update_beyonic_payment($row_id, '1');
            }
            return 1;
        case 'unsuccessful':
        case 'processed_with_errors':
            if (!$silent) {
                showMsg(__('Payment Failed!, Reason:- ' . $payment->last_error, 'pricerrtheme'));
            }
            break;
        case 'rejected':
            if (!$silent) {
                showMsg(__('Payment Failed!, Reason:- ' . $payment->rejected_reason, 'pricerrtheme'));
            }
            break;
        case 'cancelled':
            if (!$silent) {
                showMsg(__('Payment Failed!, Reason:- ' . $payment->cancelled_reason, 'pricerrtheme'));
            }
            break;
        default:
            if (!$silent) {
                showMsg(__('Payment Failed!, Reason:- Unknown Error Occurred While Approving', 'pricerrtheme'));
            }
            break;
    }
    return 2;
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

    // read database with passed user id
    $s = "select * from " . db()->prefix . "users where id='$uid'";
    $row = db()->get_results($s);
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

    switch ($done) {
        case '0':
            $ss = "update " . db()->prefix . "job_withdraw set done=" . $done . ", rejected_on='0', rejected='0', datedone='0' where id='$row_id'";
            db()->query($ss);
            break;
        case '1':
            $tm = current_time('timestamp', 0);
            $ss = "update " . db()->prefix . "job_withdraw set done=" . $done . ", datedone='$tm' where id='$row_id'";
            db()->query($ss);
            break;
        default:
            break;
    }
}

/**
 * @method // updates beyonic configuration options in the beyonic tab in the payment gateway options
 */
function PricerrTheme_add_new_beyonic_pst()
{

    if (isset($_POST['PricerrTheme_save_beyonic'])):
        echo '<div class="saved_thing">' . __('Settings saved!', 'pricerrtheme') . '</div>';
        $PricerrTheme_beyonic_api_key = trim($_POST['PricerrTheme_beyonic_api_key']);
        $PricerrTheme_beyonic_enable = $_POST['PricerrTheme_beyonic_enable'];
        $PricerrTheme_beyonic_withdraw_fee = $_POST['PricerrTheme_beyonic_withdraw_fee'];
        $PricerrTheme_beyonic_username = $_POST['PricerrTheme_beyonic_username'];
        $PricerrTheme_beyonic_password = $_POST['PricerrTheme_beyonic_password'];
        $PricerrTheme_beyonic_account_Id = $_POST['PricerrTheme_beyonic_account_Id'];
        update_option('PricerrTheme_beyonic_api_key', $PricerrTheme_beyonic_api_key);
        update_option('PricerrTheme_beyonic_enable', $PricerrTheme_beyonic_enable);
        update_option('PricerrTheme_beyonic_withdraw_fee', $PricerrTheme_beyonic_withdraw_fee);
        update_option('PricerrTheme_beyonic_username', $PricerrTheme_beyonic_username);
        update_option('PricerrTheme_beyonic_password', $PricerrTheme_beyonic_password);
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
                    <td colspan="3" style="padding:16px;"><strong>Note:</strong> Make sure Name verification checks are
                        disabled in your beyonic organisation settings (Payments will fail otherwise)
                    </td>
                </tr>
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
                    <!--TODO check system currency against account currency-->
                    <td valign=top
                        width="22"><?php PricerrTheme_theme_bullet('Account Id from your beyonic account - where payments will be charged'); ?></td>
                    <td><?php _e('Account Id:', 'PricerrTheme'); ?></td>
                    <td><input type="number" min="0" name="PricerrTheme_beyonic_account_Id"
                               value="<?php echo get_option('PricerrTheme_beyonic_account_Id'); ?>"/></td>
                </tr>
                <tr>
                    <td valign=top width="100"></td>
                    <td width="100"><strong>Rest End Point Settings</strong></td>
                </tr>
                <tr>
                    <td valign=top
                        width="22"><?php PricerrTheme_theme_bullet('User Name from your beyonic account'); ?></td>
                    <td><?php _e('User Name:', 'PricerrTheme'); ?></td>
                    <td><input type="number" min="0" name="PricerrTheme_beyonic_username"
                               value="<?php echo get_option('PricerrTheme_beyonic_username'); ?>"/></td>
                </tr>
                <tr>
                    <td valign=top
                        width="22"><?php PricerrTheme_theme_bullet('Password from your beyonic account'); ?></td>
                    <td><?php _e('Password:', 'PricerrTheme'); ?></td>
                    <td><input type="number" min="0" name="PricerrTheme_beyonic_password"
                               value="<?php echo get_option('PricerrTheme_beyonic_password'); ?>"/></td>
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
