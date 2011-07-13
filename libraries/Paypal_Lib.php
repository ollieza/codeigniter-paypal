<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); 

/**
 * PayPal CodeIgniter library
 *
 * This CodeIgniter library handles payments via PayPal including IPN responses.
 * 
 * @package   codeigniter-paypal
 * @author    Ollie Rattue, Too many tabs <orattue[at]toomanytabs.com>
 * @copyright Copyright (c) 2011, Ollie Rattue
 * @license   http://www.opensource.org/licenses/mit-license.php
 * @link      https://github.com/ollierattue/codeigniter-paypal
 */

// ------------------------------------------------------------------------

/**
 * PayPal_Lib Controller Class (Paypal IPN Class)
 *
 * This CI library is based on the Paypal PHP class by Micah Carrick
 * See www.micahcarrick.com for the most recent version of this class
 * along with any applicable sample files and other documentaion.
 *
 * This file provides a neat and simple method to interface with paypal and
 * The paypal Instant Payment Notification (IPN) interface.  This file is
 * NOT intended to make the paypal integration "plug 'n' play". It still
 * requires the developer (that should be you) to understand the paypal
 * process and know the variables you want/need to pass to paypal to
 * achieve what you want.  
 *
 * This class handles the submission of an order to paypal as well as the
 * processing an Instant Payment Notification.
 * This class enables you to mark points and calculate the time difference
 * between them.  Memory consumption can also be displayed.
 *
 * The class requires the use of the PayPal_Lib config file.
 *
 * @package     CodeIgniter
 * @subpackage  Libraries
 * @category    Commerce
 * @author      Ran Aroussi <ran@aroussi.com>
 * @copyright   Copyright (c) 2006, http://aroussi.com/ci/
 *
 */

// ------------------------------------------------------------------------

class Paypal_lib {

	var $last_error = '';			// holds the last error encountered
	var $ipn_log;				// bool: log IPN results to text file?

	var $ipn_log_file;			// filename of the IPN log
	var $ipn_log_method;
	var $ipn_response = '';		// holds the IPN response from paypal	
	var $ipn_data = array();	// array contains the POST values for IPN
	var $fields = array();		// array holds the fields to submit to paypal
    var $paypal_url = '';
    var $paypal_cmd = '_xclick';
	var $submit_btn = '';		// Image/Form button
	var $button_path = '';		// The path of the buttons
	
	var $CI;
	
    // Allowed order fields. No point making this static as PHP arrays can always be modified later on anyway.
    private $orderFields = array('notify_version', 'verify_sign', 'test_ipn', 'protection_eligibility', 'charset', 'btn_id', 'address_city', 'address_country', 'address_country_code', 'address_name', 'address_state', 'address_status', 'address_street', 'address_zip', 'first_name', 'last_name', 'payer_business_name', 'payer_email', 'payer_id', 'payer_status', 'contact_phone', 'residence_country', 'business', 'receiver_email', 'receiver_id', 'custom', 'invoice', 'memo', 'option_name_1', 'option_name_2', 'option_selection1', 'option_selection2', 'tax decimal', 'auth_id', 'auth_exp', 'auth_amount', 'auth_status', 'num_cart_items', 'parent_txn_id', 'payment_date', 'payment_status', 'payment_type', 'pending_reason', 'reason_code', 'remaining_settle', 'shipping_method', 'shipping', 'transaction_entity', 'txn_id', 'txn_type', 'exchange_rate', 'mc_currency', 'mc_fee', 'mc_gross', 'mc_handling', 'mc_shipping', 'payment_fee', 'payment_gross', 'settle_amount', 'settle_currency', 'auction_buyer_id', 'auction_closing_date', 'auction_multi_item', 'for_auction', 'subscr_date', 'subscr_effective', 'period1', 'period2', 'period3', 'amount1', 'amount2', 'amount3', 'mc_amount1', 'mc_amount2', 'mc_amount3', 'recurring', 'reattempt', 'retry_at', 'recur_times', 'username', 'password', 'subscr_id', 'case_id', 'case_type', 'case_creation_date', 'order_status', 'discount', 'shipping_discount', 'ipn_track_id', 'transaction_subject');
	
	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->helper('url');
		$this->CI->load->helper('form');
		$this->CI->load->config('paypallib_config');
		
		$this->paypal_url = ($this->CI->config->item('paypal_lib_live')) ? 'https://www.paypal.com/cgi-bin/webscr' : 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        $this->ipn_log_file = $this->CI->config->item('paypal_lib_ipn_log_file');
		$this->ipn_log = $this->CI->config->item('paypal_lib_ipn_log'); 
		$this->button_path = $this->CI->config->item('paypal_lib_button_path');
		$this->ipn_log_method = $this->CI->config->item('paypal_lib_ipn_log_method');
		
		$this->add_field('currency_code', $this->CI->config->item('paypal_lib_currency_code'));
	    $this->add_field('quantity', '1');
		$this->button('Pay Now!');
	}

    /**
     * Add a hidden input field to the paypal form
     * @param string field name
     * @param string value of input
     * @return void
     */
	function add_field($field, $value) 
	{
		// adds a key=>value pair to the fields array, which is what will be 
		// sent to paypal as POST variables.  If the value is already in the 
		// array, it will be overwritten.
		$this->fields[$field] = $value;
	}
    
    /**
     * Add a text submit button to form
     * @param string text for button
     * @return void
     */
	function button($value)
	{
		// changes the default caption of the submit button
		$this->submit_btn = form_submit('pp_submit', $value);
	}
    
    /**
     * Unset form variables
     * @param none
     * @return void
     */
    public function clear(){
        $this->fields = array();
    }
    
    /**
     * Add a Paypal image submit button to form
     * @param string filename of image
     * @return void
     */
	function image($file)
	{
		$this->submit_btn = '<input type="image" name="add" src="' . site_url($this->button_path .'/'. $file) . '" border="0" />';
	}
        
    /**
     * Initialize the configuration variables
     * @param array
     * @return void
     */
     public function initialize( $options = array() ){
        if(is_array($options)){
            foreach($options as $key => $value){
                $this->$key = $value;
            }
        }
        
        // populate $fields array with a few default values.  See the paypal
		// documentation for a list of fields and their data types. These defaul
		// values can be overwritten by the calling script.
		$this->add_field('rm','2');			  // Return method = POST
		$this->add_field('cmd',$this->paypal_cmd);
        
        return;
     }
     
     /**
      * Add fields for a subscription button
      * Defaults
      * a3 = $10
      * p3 = 12
      * t3 = months
      * sra = 1 reattempt failed payment
      * @param array of key=>values for fields
      * @return string html of form
      */
     public function add_subscription( $options = array() ){
        
        //Merge default values with values passed to function
        $options = array_merge( array('a3' => '10',
                                      'p3' => '12',
                                      't3' => 'M',
                                      'sra'  => TRUE,
                                      'custom' => rand()
                                    ), $options);
                                    
        foreach ($options as $key => $value) {
            $this->add_field($key, $value);
        }
        
        return $this->paypal_form();
        
     }
    
    /**
     * Generate a complete html page with a single paypal form
     * @param none
     * @return void
     */
	function paypal_auto_form() 
	{
		// this function actually generates an entire HTML page consisting of
		// a form with hidden elements which is submitted to paypal via the 
		// BODY element's onLoad attribute.  We do this so that you can validate
		// any POST vars from you custom form before submitting to paypal.  So 
		// basically, you'll have your own form which is submitted to your script
		// to validate the data, which in turn calls this function to create
		// another hidden form and submit to paypal.

		$this->button('Click here if you\'re not automatically redirected...');

		echo '<html>' . "\n";
		echo '<head><title>Processing Payment...</title></head>' . "\n";
		echo '<body onLoad="document.forms[\'paypal_auto_form\'].submit();">' . "\n";
		echo '<p>Please wait, your order is being processed and you will be redirected to the paypal website.</p>' . "\n";
		echo $this->paypal_form('paypal_auto_form');
		echo '</body></html>';
	}


    /**
     * Generate Paypal form returns form tag with all hidden inputs
     * @param string form name
     * @return string html for form
     */
	function paypal_form($form_name='paypal_form') 
	{
		$str = '';
		$str .= '<form method="post" action="'.$this->paypal_url.'" name="'.$form_name.'"/>' . "\n";
		foreach ($this->fields as $name => $value)
			$str .= form_hidden($name, $value) . "\n";
		$str .= '<p>'. $this->submit_btn . '</p>';
		$str .= form_close() . "\n";

		return $str;
	}
	
    
    /**
     * Validate a IPN post back from Paypal servers
     * @param none $_POST from paypal
     * @return bool
     */
	function validate_ipn()
	{
		// parse the paypal URL
		$url_parsed = parse_url($this->paypal_url);		  

		// generate the post string from the _POST vars aswell as load the
		// _POST vars into an arry so we can play with them from the calling
		// script.
		$post_string = '';	 
		if ($_POST)
		{
			foreach ($_POST as $field => $value)
			{ 
				$this->ipn_data[$field] = $value;
				$post_string .= $field.'='.urlencode(stripslashes($value)).'&'; 
			}
		}
		
		$post_string.="cmd=_notify-validate"; // append ipn command
        
		// open the connection to paypal
		$fp = fsockopen($url_parsed['host'],"80",$err_num,$err_str,30); 
		if(!$fp)
		{
			// could not open the connection.  If loggin is on, the error message
			// will be in the log.
			$this->last_error = "fsockopen error no. $errnum: $errstr";
			$this->log_ipn_results(false);		 
			return false;
		} 
		else
		{ 
			// Post the data back to paypal
			fputs($fp, "POST $url_parsed[path] HTTP/1.1\r\n"); 
			fputs($fp, "Host: $url_parsed[host]\r\n"); 
			fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n"); 
			fputs($fp, "Content-length: ".strlen($post_string)."\r\n"); 
			fputs($fp, "Connection: close\r\n\r\n"); 
			fputs($fp, $post_string . "\r\n\r\n"); 

			// loop through the response from the server and append to variable
			while(!feof($fp))
				$this->ipn_response .= fgets($fp, 1024); 

			fclose($fp); // close connection
		}
        
        // $data['post_string'] = $post_string;
        // $data['host']        = $url_parsed['host'];
        // $data['ipn_response']= $this->ipn_response;
        // $data['server']      = $_SERVER;
        // 
        // $insert = array('paypal_log_data' => serialize($data),
        //                 'paypal_log_date_modified' => date('Y-m-d H:i:s')
        // );
        // $this->CI->db->insert('paypal_log', $insert);
        
		if (preg_match("/VERIFIED/i", $this->ipn_response))
		{
			// Valid IPN transaction.
			if ($this->ipn_log_method == 'db')
			{
				return $this->log_ipn_results(true);
			}
			
			return true;		 
		} 
		else 
		{
			// Invalid IPN transaction.  Check the log for details.
			$this->last_error = 'IPN Validation Failed.';
			$this->log_ipn_results(false);	
			return false;
		}
	}


    /**
     * Log IPN results from request in a txt file
     * @param bool
     * @return void
     */
	function log_ipn_results($success) 
	{
		if (!$this->ipn_log) return;  // is logging turned off?

		// Timestamp
		$text = '['.date('m/d/Y g:i A').'] - '; 

		// Success or failure being logged?
		if ($success) $text .= "SUCCESS!\n";
		else $text .= 'FAIL: '.$this->last_error."\n";

		// Log the POST variables
		$text .= "IPN POST Vars from Paypal:\n";
		foreach ($this->ipn_data as $key=>$value)
			$text .= "$key=$value, ";

		// Log the response from the paypal server
		$text .= "\nIPN Response from Paypal Server:\n ".$this->ipn_response;

		// Write to log
		if ($this->ipn_log_method == 'file')
		{
			$fp=fopen($this->ipn_log_file,'a');
			fwrite($fp, $text . "\n\n"); 

			fclose($fp);  // close file
			
			return;		
		}
        
		if ($this->ipn_log_method == 'db')
		{
			//Add to DB
	        $data['ipn_response'] = $this->ipn_response;
	        $data['text']         = $text;
	        $data['ipn_data']     = $this->ipn_data;
	        
			$log_data = array(
							'paypal_log_data' 			=> serialize($data),
	                        'paypal_log_created' 		=> date('Y-m-d H:i:s')
	        );
	     
	  		// First extract the actual order record itself
        	foreach ($this->ipn_data as $key => $value)
	        {
	            // Only add the field if it's in our whitelist.
	            // We use a whitelist because PayPal adds new fields to the IPN system
	            // at random/without warning, and if we don't have a whitelist then
	            // the database insert/update will then break
	            if (in_array($key, $this->orderFields))
	            {
	                $log_data[$key] = $value;
	            }
	        }
    
	   		$this->CI->db->insert('paypal_log', $log_data);
	
			return $this->CI->db->insert_id();
		}
	}

    /**
     * Debug output echos out html
     * @param none
     * @return void
     */
	function dump() 
	{
		// Used for debugging, this function will output all the field/value pairs
		// that are currently defined in the instance of the class using the
		// add_field() function.

		ksort($this->fields);
		echo '<h2>ppal->dump() Output:</h2>' . "\n";
		echo '<code style="font: 12px Monaco, \'Courier New\', Verdana, Sans-serif;  background: #f9f9f9; border: 1px solid #D0D0D0; color: #002166; display: block; margin: 14px 0; padding: 12px 10px;">' . "\n";
		foreach ($this->fields as $key => $value) echo '<strong>'. $key .'</strong>:	'. urldecode($value) .'<br/>';
		echo "</code>\n";
	}

}

?>