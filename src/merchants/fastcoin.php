<?php
/**
 * @file Fastcoin for the WP e-Commerce shopping cart plugin for WordPress
 * @author m0gliE

$nzshpcrt_gateways[$num]['name'] = 'Fastcoin';
$nzshpcrt_gateways[$num]['internalname'] = 'fastcoin';
$nzshpcrt_gateways[$num]['function'] = 'gateway_fastcoin';
$nzshpcrt_gateways[$num]['form'] = "form_fastcoin";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_fastcoin";
$nzshpcrt_gateways[$num]['payment_type'] = "credit_card";

add_filter("the_content", "fastcoin_checkout_complete_display_filter", 99);
add_filter("wp_mail", "fastcoin_checkout_complete_mail_filter", 99);
add_filter("cron_schedules", "fastcoin_create_cron_schedule", 10);
add_action("fastcoin_cron", "fastcoin_cron");

register_deactivation_hook(__FILE__ . DIRECTORY_SEPARATOR . "../wp-shopping-cart.php", "fastcoin_disable_cron");

/**
 * Set up a custom cron schedule to run every 5 minutes.
 *
 * Invoked via the cron_schedules filter.
 *
 * @param array $schedules
 */
function fastcoin_create_cron_schedule($schedules = '') {
  $schedules['every5minutes'] = array(
    'interval' => 300,
    'display' => __('Every five minutes'),
  );
  return $schedules;
}

/**
 * Cancel the Fastcoin processing cron job.
 *
 * Invoked at deactivation of WP e-Commerce
 */
function fastcoin_disable_cron() {
  wp_clear_scheduled_hook("fastcoin_cron");
}

function fastcoin_debug($message) {
  error_log($message);
}

/**
 * Cron job to process outstanding Fastcoin transactions.
 */
function fastcoin_cron() {
  /*
   * Find transactions where purchase status = 1 and gateway = fastcoin.
   * Fastcoin address for the transaction is stored in transactid
   */
  global $wpdb;
  fastcoin_debug("entering cron");
  $transactions = $wpdb->get_results("SELECT id,totalprice,sessionid,transactid,date FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE gateway='fastcoin' AND processed='1'");
  if (count($transactions) < 1)
    return;
  fastcoin_debug("have transactions to process");
  include_once("library/fastcoin.inc");
  $fastcoin_client = new FastcoinClient(get_option("fastcoin_scheme"),
    get_option("fastcoin_username"),
    get_option("fastcoin_password"),
    get_option("fastcoin_address"),
    get_option("fastcoin_port"),
    get_option("fastcoin_certificate_path"));

  if (TRUE !== ($fault = $fastcoin_client->can_connect())) {
    error_log('The Fastcoin server is presently unavailable. Fault: ' . $fault);
    return;
  }
  fastcoin_debug("server reachable");
  foreach ($transactions as $transaction) {
    $address = $transaction->transactid;
    $order_id = $transaction->id;
    $order_total = $transaction->totalprice;
    $sessionid = $transaction->sessionid;
    $order_date = $transaction->date;
    fastcoin_debug("processing: " . var_export($transaction, TRUE));
    try {
      $paid = $fastcoin_client->query("getreceivedbyaddress", $address, get_option("fastcoin_confirms"));
    } catch (FastcoinClientException $e) {
      error_log("Fastcoin server communication failed on getreceivedbyaddress " . $address . " with fault string " . $e->getMessage());
      continue;
    }
    if ($paid >= $order_total) {
      fastcoin_debug("paid in full");
      // PAID IN FULL
      // Update payment log
      $wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET processed='2' WHERE id='" . $order_id . "'");
      // Email customer
      transaction_results($sessionid, false);
      continue;
    }
    if (time() > $order_date + get_option("fastcoin_timeout") * 60 * 60) {
      fastcoin_debug("order expired");
      // ORDER EXPIRED
      // Update payment log
      $wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET processed='5' WHERE id='" . $order_id . "'");
      // Can't email the customer via transaction_results
      // TODO: Email the customer, delete the order
    }
  }
  fastcoin_debug("leaving cron");
}

function fastcoin_checkout_complete_display_filter($content = "") {
  if (!isset($_SESSION['fastcoin_address_display']) || empty($_SESSION['fastcoin_address_display']))
    return $content;
  $cart = unserialize($_SESSION['wpsc_cart']);
  $content = preg_replace('/@@TOTAL@@/', $cart->total_price, $content);
  $content = preg_replace('/@@ADDRESS@@/', $_SESSION['fastcoin_address_display'], $content);
  $content = preg_replace('/@@TIMEOUT@@/', get_option('fastcoin_timeout'), $content);
  $content = preg_replace('/@@CONFIRMATIONS@@/', get_option('fastcoin_confirms'), $content);
  unset($_SESSION['fastcoin_address_display']);
  return $content;
}

function fastcoin_checkout_complete_mail_filter($mail) {
  if (!isset($_SESSION['fastcoin_address_mail']) || empty($_SESSION['fastcoin_address_mail']))
    return $mail;
  $cart = unserialize($_SESSION['wpsc_cart']);
  $mail['message'] = preg_replace('/@@TOTAL@@/', $cart->total_price, $mail['message']);
  $mail['message'] = preg_replace('/@@ADDRESS@@/', $_SESSION['fastcoin_address_mail'], $mail['message']);
  $mail['message'] = preg_replace('/@@TIMEOUT@@/', get_option('fastcoin_timeout'), $mail['message']);
  $mail['message'] = preg_replace('/@@CONFIRMATIONS@@/', get_option('fastcoin_confirms'), $mail['message']);
  unset($_SESSION['fastcoin_address_mail']);
  return $mail;
}

function fastcoin_checkout_fail($sessionid, $message, $fault = "") {
  global $wpdb;
  $wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET processed='5' WHERE sessionid=" . $sessionid);
  $_SESSION['WpscGatewayErrorMessage'] = $message;
  $_SESSION['fastcoin'] = 'fail';
  error_log($message . ": " . $fault);
  header("Location: " . get_option("checkout_url"));
}

/**
 * Process Fastcoin checkout.
 *
 * @param string $separator
 * @param integer $sessionid
 * @todo Document better
 */
function gateway_fastcoin($separator, $sessionid) {
  global $wpdb, $wpsc_cart;

  include_once("library/fastcoin.inc");
  $fastcoin_client = new FastcoinClient(get_option("fastcoin_scheme"),
    get_option("fastcoin_username"),
    get_option("fastcoin_password"),
    get_option("fastcoin_address"),
    get_option("fastcoin_port"),
    get_option("fastcoin_certificate_path"));

  if (TRUE !== ($fault = $fastcoin_client->can_connect())) {
    fastcoin_checkout_fail($session, 'The Fastcoin server is presently unavailable. Please contact the site administrator.', $fault);
    return;
  }

  $row = $wpdb->get_row("SELECT id,totalprice FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE sessionid=" . $sessionid);
  $label = $row->id . " " . $row->totalprice;
  try {
    $address = $fastcoin_client->query("getnewaddress", $label);
  } catch (FastcoinClientException $e) {
    fastcoin_checkout_fail($session, 'The Fastcoin server is presently unavailable. Please contact the site administrator.', $e->getMessage());
    return;
  }
  if (!Fastcoin::checkAddress($address)) {
    fastcoin_checkout_fail($session, 'The Fastcoin server returned an invalid address. Please contact the site administrator.', $e->getMessage());
    return;
  }
  //var_dump($_SESSION);
  unset($_SESSION['WpscGatewayErrorMessage']);
  // Set the transaction to pending payment and log the Fastcoin address as its transaction ID
  $wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET processed='1', transactid='" . $address . "' WHERE sessionid=" . $sessionid);
  $_SESSION['fastcoin'] = 'success';
  $_SESSION['fastcoin_address_display'] = $address;
  $_SESSION['fastcoin_address_mail'] = $address;
  header("Location: " . get_option('transact_url') . $separator . "sessionid=" . $sessionid);
  exit();
}

/**
 * Set Fastcoin payment options and start the cronjob.
 * @todo validate values
 */
function submit_fastcoin() {
  $options = array(
    "fastcoin_scheme",
    "fastcoin_certificate_path",
    "fastcoin_username",
    "fastcoin_password",
    "fastcoin_port",
    "fastcoin_address",
    "fastcoin_timeout",
    "fastcoin_confirms",
    "payment_instructions",
  );
  foreach ($options as $o)
    if ($_POST[$o] != NULL)
      update_option($o, $_POST[$o]);
  wp_clear_scheduled_hook("fastcoin_cron");
  wp_schedule_event(time(), "every5minutes", "fastcoin_cron");
  return true;
}

/**
 * Produce the HTML for the Fastcoin settings form.
 */
function form_fastcoin() {
  global $wpdb;
  $fastcoin_scheme = (get_option('fastcoin_scheme') == '' ? 'http' : get_option('fastcoin_scheme'));
  $fastcoin_certificate_path = get_option('fastcoin_certificate_path');
  $fastcoin_username = get_option('fastcoin_username');
  $fastcoin_password = get_option('fastcoin_password');
  $fastcoin_address = (get_option('fastcoin_address') == '' ? 'localhost' : get_option('fastcoin_address'));
  $fastcoin_port = (get_option('fastcoin_port') == '' ? '9527' : get_option('fastcoin_port'));
  $fastcoin_timeout = (get_option('fastcoin_timeout') == '' ? '72' : get_option('fastcoin_timeout'));
  $fastcoin_confirms = (get_option('fastcoin_confirms') == '' ? '3' : get_option('fastcoin_confirms'));
  if (get_option('payment_instructions') != '')
    $payment_instructions = get_option('payment_instructions');
  else {
    $payment_instructions = '<strong>Please send your payment of TRC @@TOTAL@@ to Fastcoin address @@ADDRESS@@.</strong> ';
    $payment_instructions .= 'If your payment is not received within @@TIMEOUT@@ hour(s) with at least @@CONFIRMATIONS@@ network confirmations, ';
    $payment_instructions .= 'your transaction will be canceled.';
  }

  // Create the Fastcoin currency if it doesn't already exist
  $sql = "SELECT currency FROM " . WPSC_TABLE_CURRENCY_LIST . " WHERE currency='Fastcoin'";
  if (!$wpdb->get_row($sql)) {
    $sql = "INSERT INTO " . WPSC_TABLE_CURRENCY_LIST . " VALUES (NULL, 'Fastcoin', 'TC', 'Fastcoin', '', '', 'TRC', '0', '0', 'antarctica', '1')";
    $wpdb->query($sql);
  }

  $output = "
		<tr>
			<td>&nbsp;</td>
			<td><small>Connection data for your fastcoin server HTTP-JSON-RPC interface.</small></td>
		</tr>
		<tr>
			<td>Server scheme (HTTP or HTTPS)</td>
			<td><input type='text' size='40' value='"
    . $fastcoin_scheme . "' name='fastcoin_scheme' /></td>
		</tr>
		<tr>
			<td>SSL certificate path</td>
			<td><input type='text' size='40' value='"
    . $fastcoin_certificate_path . "' name='fastcoin_certificate_path' /></td>
		</tr>
		<tr>
			<td>Server username</td>
			<td><input type='text' size='40' value='"
    . $fastcoin_username . "' name='fastcoin_username' /></td>
		</tr>
		<tr>
			<td>Server password</td>
			<td><input type='text' size='40' value='"
    . $fastcoin_password . "' name='fastcoin_password' /></td>
		</tr>
		<tr>
			<td>Server address (usually localhost)</td>
			<td><input type='text' size='40' value='"
    . $fastcoin_address . "' name='fastcoin_address' /></td>
		</tr>
		<tr>
			<td>Server port (usually 8338)</td>
			<td><input type='text' size='40' value='"
    . $fastcoin_port . "' name='fastcoin_port' /></td>
		</tr>
		<tr>
			<td>Transaction timeout (hours)</td>
			<td><input type='text' size='40' value='"
    . $fastcoin_timeout . "' name='fastcoin_timeout' /></td>
		</tr>
		<tr>
			<td>Transaction confirmations required</td>
			<td><input type='text' size='40' value='"
    . $fastcoin_confirms . "' name='fastcoin_confirms' /></td>
		</tr>
		<tr>
			<td colspan='2'>
				<strong>Enter the template for payment instructions to be give to the customer on checkout.</strong><br />
				<textarea cols='40' rows='9' name='wpsc_options[payment_instructions]'>"
    . $payment_instructions . "</textarea><br />
    			Valid template tags:
    			<ul>
    				<li>@@TOTAL@@ - The order total</li>
    				<li>@@ADDRESS@@ - The Fastcoin address generated for the transaction</li>
    				<li>@@TIMEOUT@@ - Transaction timeout (hours)</li>
    				<li>@@CONFIRMATIONS@@ - Transaction confirmations required</li>
    			</ul>
			</td>
		</tr>
		<tr>
			<td colspan='2'>
				Like Fastcoin for WP e-Commerce? Your gifts to <a href="fastcoin:fmzFf7rCAzYFWnbzcLpoBBf1P9mzZfNmmm">fmzFf7rCAzYFWnbzcLpoBBf1P9mzZfNmmm</a> are <strong>greatly</strong> appreciated. Thank you!
			</td>
		</tr>
	";
  return $output;
}
?>
