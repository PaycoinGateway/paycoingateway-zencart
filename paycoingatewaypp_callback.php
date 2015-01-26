<?php

require 'includes/application_top.php';
require_once(dirname(__FILE__) . "/includes/modules/payment/paycoingateway/paycoin.php");

$type = $_GET['type'];

if($type == "success") {

  // Customer's browser - checkout was successful
  unset($_SESSION['paycoingatewaypp_order_id']);
  zen_redirect(zen_href_link('checkout_success'));
} if($type == "cancel") {

  // Customer's browser - they clicked Cancel during checkout
  unset($_SESSION['aycoingatewaypp_order_id']);
  zen_redirect(zen_href_link('index'));
} else if($type == "oauth") { 

  // Admin connecting OAuth account
  $hostPart = "http" . (($_SERVER['SERVER_PORT']==443) ? "s://" : "://") . $_SERVER['HTTP_HOST'];
  $pathParts = explode("?", $_SERVER['REQUEST_URI']);
  $url = $hostPart . $pathParts[0] . "?type=oauth&after=" . urlencode($_GET['after']);
  $oauth = new PaycoinGateway_OAuth(MODULE_PAYMENT_PAYCOINGATEWAY_OAUTH_CLIENTID, MODULE_PAYMENT_PAYCOINGATEWAY_OAUTH_CLIENTSECRET, $url);
  
  try {
    $tokens = $oauth->getTokens($_GET['code']);
  } catch (Exception $e) {
    echo 'Could not authenticate. Please try again. ' . $e->getMessage();
    return;
  }
  
  global $db;
  $db->Execute("update ". TABLE_CONFIGURATION. " set configuration_value = '" . $db->prepare_input(serialize($tokens)) . "' where configuration_key = 'MODULE_PAYMENT_PAYCOINGATEWAY_OAUTH'");
  
  zen_redirect($hostPart . $_GET['after']);
} else if($type == MODULE_PAYMENT_PAYCOINGATEWAY_CALLBACK_SECRET) {

  // From paycoingateway - callback
  $postBody = json_decode(file_get_contents('php://input'));
  $paycoingateway = new PaycoinGateway(new PaycoinGateway_OAuth(MODULE_PAYMENT_PAYCOINGATEWAY_OAUTH_CLIENTID, MODULE_PAYMENT_PAYCOINGATEWAY_OAUTH_CLIENTSECRET, null), unserialize(MODULE_PAYMENT_PAYCOINGATEWAY_OAUTH));
  $orderId = $postBody->order->id;
  $order = $paycoingateway->getOrder($orderId);
  
  if($order == null) {
    // Callback is no good
    header("HTTP/1.1 500 Internal Server Error");
    return;
  }
  
  // Update order status
  $db->Execute("update ". TABLE_ORDERS. " set orders_status = " . MODULE_PAYMENT_PAYCOINGATEWAY_COMPLETE_STATUS_ID . " where orders_id = ". intval($order->custom));
}
