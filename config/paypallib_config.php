<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

// ------------------------------------------------------------------------
// Paypal (Paypal IPN Class)
// ------------------------------------------------------------------------

// If (and where) to log ipn to file
$config['paypal_lib_ipn_log_file'] = APPPATH . 'logs/paypal_ipn.log';
$config['paypal_lib_ipn_log'] = TRUE;
$config['paypal_lib_ipn_log_method'] = 'db';

// Where are the buttons located at 
$config['paypal_lib_button_path'] = 'images/buttons';

// What is the default currency?
$config['paypal_lib_currency_code'] = 'EUR';

$config['paypal_lib_live'] = FALSE;
?>