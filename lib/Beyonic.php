<?php
define("BEYONIC_CLIENT_VERSION", "0.0.15");

if (!function_exists('curl_init')) {
  throw new Exception('Beyonic requires the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
  throw new Exception('Beyonic requires the JSON PHP extension.');
}

// Beyonic Primary Interface
require_once(__DIR__ . '/Beyonic/Beyonic.php');

// Beyonic API endpoints
require_once(__DIR__ . '/Beyonic/Endpoint_Wrapper.php');
require_once(__DIR__ . '/Beyonic/Webhook.php');
require_once(__DIR__ . '/Beyonic/Payment.php');
require_once(__DIR__ . '/Beyonic/Collection.php');
require_once(__DIR__ . '/Beyonic/Collection_Request.php');
require_once(__DIR__ . '/Beyonic/Contact.php');
require_once(__DIR__ . '/Beyonic/Account.php');
require_once(__DIR__ . '/Beyonic/Transaction.php');
require_once(__DIR__ . '/Beyonic/Currency.php');
require_once(__DIR__ . '/Beyonic/Network.php');
require_once(__DIR__ . '/Beyonic/Beyonic_Exception.php');
