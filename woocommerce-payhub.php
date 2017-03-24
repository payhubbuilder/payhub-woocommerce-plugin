<?php
/*
Plugin Name: PayHub Gateway Plugin for WooCommerce
Plugin URI: http://developer.payhub.com/
Description: This plugin allows you to accept credit card payments through PayHub in your WooCommerce storefront.
Version: 1.0.17
Author: PayHub
*/

$path_to_IncludeClases=WP_PLUGIN_DIR . "/" . plugin_basename( dirname(__FILE__)) . "/com/payhub/ws/extra/includeClasses.php";
include_once $path_to_IncludeClases;

add_action('plugins_loaded', 'woocommerce_payhub_init', 0);

function woocommerce_payhub_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	
	//require_once(WP_PLUGIN_DIR . "/" . plugin_basename( dirname(__FILE__)) . '/class/payhubTransaction.class.php');

	/**
 	 * Gateway class
 	 **/
	class WC_PayHub_Gateway extends WC_Payment_Gateway {
	
		var $avaiable_countries = array(
			'GB' => array(
				'Visa',
				'MasterCard',
				'Discover',
				'American Express'
			),
			'US' => array(
				'Visa',
				'MasterCard',
				'Discover',
				'American Express'
			),
			'CA' => array(
				'Visa',
				'MasterCard',
				'Discover',
				'American Express'
			)
		);
		//var $api_username;
		var $api_password;
		var $orgid;
		var $demo;
		var $terminal_id;

		var $api_password_demo;
		var $org_id_demo;
		var $terminal_id_demo;


		var $card_data;
		var $card_cvv;
		var $card_exp_month;
		var $card_exp_year;
		var $response;


		function __construct() { 				
			$this->id	= 'payhub';
			$this->method_title 	= __('PayHub', 'woothemes');
			$this->icon = WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/PoweredbyPayHubCards.png';
			$this->has_fields=true;
			
			$this->supports = array(
									  'default_credit_card_form',
									  'refunds'
									);

			//$this->supports[] = 'default_credit_card_form';
			// Load the form fields
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();
			
			// Get setting values
			$this->title 			= $this->settings['title'];
			$this->description 		= $this->settings['description'];
			$this->demo = $this->settings['demo'];
            $this->disable_payhub_email = $this->settings['disable_payhub_email'];
			$this->enabled 			= $this->settings['enabled'];
			//$this->api_username 	= $this->settings['api_username'];
			$this->api_password 	= $this->settings['api_password'];
			$this->orgid 	= $this->settings['orgid'];
			$this->tid 	= $this->settings['terminal_id'];


			$this->api_password_demo 	= $this->settings['api_password_demo'];
			$this->org_id_demo 	= $this->settings['org_id_demo'];
			$this->terminal_id_demo 	= $this->settings['terminal_id_demo'];
			

			// Hooks
			add_action( 'admin_notices', array( &$this, 'ssl_check') );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options') );
			add_action( 'woocommerce_thankyou_cheque', array(&$this, 'thankyou_page' ));
		}

		/**
	 	 * Check if SSL is enabled and notify the user if SSL is not enabled
	 	 **/

		function ssl_check() {		
			if (get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') {
				echo '<div class="error"><p>'.sprintf(__('PayHub is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), admin_url('admin.php?page=woocommerce')).'</p></div>';
			}
		}

		/*
		 * Check to see if a specific WC feature is supported.
		 */			
		function supports( $feature ) {
			return apply_filters( 'woocommerce_payment_gateway_supports', in_array( $feature, $this->supports) ? true : false, $feature, $this);
		}

		/**
		 * Check for version 2.1 or greater.  We only support 2.x so if this is false 
		 * then the version should be 2.0.x
		 */
		function isWcVersionTwoPointOneOrGreater() {
			global $woocommerce;
			$newer_version_threshold = "2.1.0";

			if (version_compare($woocommerce->version, $newer_version_threshold, ">=" )) return true;

			return false;
		}

		/**
     	 * Initialize Gateway Settings Form Fields
     	 */
	    function init_form_fields() {		    
	    	$this->form_fields = array(
				'title' => array(
					'title' => __( 'Title', 'woothemes' ), 
					'type' => 'text', 
					'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ), 
					'default' => __( 'PayHub, Inc', 'woothemes' ),
					), 
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woothemes' ), 
					'label' => __( 'Enable PayHub', 'woothemes' ), 
					'type' => 'checkbox', 
					'description' => '', 
					'default' => 'no'
					),
				'demo' => array(
					'title' => __( 'PayHub Demo', 'woothemes' ), 
					'label' => __( 'Enable Demo Mode', 'woothemes' ), 
					'type' => 'checkbox',  
					'description' => __('This turns on Demo Mode, where all transactions will go to our demo server.  While this mode is on, you can use any credit card number, but must use the following CVVs for the following card types.  VISA = 999, Mastercard = 998, AMEX = 9997, and Discover/Diners = 996', 'woothemes'), 
					'default' => 'no'
					),
				'description' => array(
					'title' => __( 'Description', 'woothemes' ), 
					'type' => 'text', 
					'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ), 
					'default' => 'We accept Visa, Mastercard, & Discover'
					),  
				// 'api_username' => array(
				// 	'title' => __( 'API Username', 'woothemes' ), 
				// 	'type' => 'text', 
				// 	'description' => __( 'Get your API Login from PayHub.', 'woothemes' ), 
				// 	'default' => ''
				// 	), 
				'api_password' => array(
					'title' => __( '3rd PartyAPI token', 'woothemes' ), 
					'type' => 'text', 
					'description' => __( 'Get your API token from PayHub.', 'woothemes' ), 
					'default' => ''
					),
				'orgid' => array(
					'title' => __( 'OrgID', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'This is your organization ID', 'woothemes' ),
					'default' => '00000'
					),
				'terminal_id' => array(
					'title' => __( 'Terminal ID', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'Get your terminal ID from PayHub.', 'woothemes' ),
					'default' => '0000'
					),		
				'disable_payhub_email' => array(
					'title' => __( 'Disable Email from PayHub System', 'wootheme'),
					'type' => 'checkbox',
					'description' => __ ( 'If checked, the email address will not be sent to PayHub, therefore disabling the sending of a receipt from PayHub', 'woothemes'),
					'default' => 'no'
					),
				'api_password_demo' => array(
					'title' => __( '3rd PartyAPI token for Demo', 'woothemes' ), 
					'type' => 'text', 
					'description' => __( 'Get your API token from PayHub.', 'woothemes' ), 
					'default' => '26c4296b-0e79-4ea5-941c-a81ece6e15a3'
					),
				'org_id_demo' => array(
					'title' => __( 'OrgID for Demo', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'This is your organization ID', 'woothemes' ),
					'default' => '10002'
					),
				'terminal_id_demo' => array(
					'title' => __( 'Terminal ID for Demo', 'woothemes' ),
					'description' => __( 'Get your terminal ID from PayHub.', 'woothemes' ),
					'type' => 'text',
					'default' => '2'
					),
				'testConnection' => array(
					'title' => __( 'Test Connection', 'woothemes' ), 
					'type' => 'button'
					)
				);
	    }
	  
	    /**
		 * Admin Panel Options 
		 * - Options for bits like 'title' and availability on a country-by-country basis
		*/
		function admin_options() {
	    	?>
	    	<h3><?php _e( 'PayHub', 'woothemes' ); ?></h3>
	    	<p><?php _e( 'Payhub works by adding credit card fields on the checkout and then sending the details to our webservice for verification. You must first have a PayHub Account to accept credit card and debit card payments. Please contact x to setup an account. If you have any questions you can contact us at (800) 944-1399 M-F from 8am - 5 pm PST or email us at wecare@payhub.com</a> anytime.  ', 'woothemes' ); ?></p>
	    	<table class="form-table">
	    		<?php $this->generate_settings_html(); ?>
			</table><!--/.form-table-->
	    	<?php
	    }
		
		/**
	     * Payment form on checkout page
	     */


		

		/**
	 	 * Add the Gateway to WooCommerce
	 	 **/
		function process_payment( $order_id ) {	
			global $woocommerce;
			$order = new WC_Order( $order_id );	
			$mode = $this->demo;
            $doNotSendCustomerEmailToPayHub = $this->disable_payhub_email;								
			//Defining data for the SALE transaction
			// Merchant data (obtained from the payHub Virtual Terminal (3rd party integration)
			$merchant = new Merchant();
			
			if ($mode == "yes") {
				$WsURL="https://sandbox-api.payhub.com/api/v2/";
				$oauth_token = $this->api_password_demo;
				$merchant->setOrganizationId($this->org_id_demo);
				$merchant->setTerminalId($this->terminal_id_demo);
			} 
		    else {
				$WsURL="https://dc1-api.payhub.com/api/v2/";
				$oauth_token = $this->api_password;
				$merchant->setOrganizationId($this->orgid);
				$merchant->setTerminalId($this->tid);
			}
			//Defining the Web Service URL
			//$WsURL="https://staging-api.payhub.com/api/v2/";
			

			// bill data
			$bill= new Bill();
			$bill->setBaseAmount($order->get_total() - $order->get_total_tax() - $order->get_total_shipping());
			$bill->setShippingAmount($order->get_total_shipping());
			$bill->setTaxAmount($order->get_total_tax());
			$bill->setNote( "Order id: ".$order_id . ", User Id:" . $order->user_id);
			


			if(validate_fields()){
				//Credit card data
				$card_data = new CardData();

				$x_card_no = str_replace( array(' ', '-' ), '', $_POST['payhub-card-number'] );
				$x_card_cvv=(isset($_POST['payhub-card-cvc'])) ? $_POST ['payhub-card-cvc'] : '';

				$x_exp_date_aux=isset($_POST['payhub-card-expiry']) ? explode ("/",$_POST['payhub-card-expiry']) :  array('','');
				$card_exp_month		=  str_replace( array(' ', '-' ), '',$x_exp_date_aux[0]);
				$card_exp_year 		=  str_replace( array(' ', '-' ), '',$x_exp_date_aux[1]);

				if(strlen($card_exp_year)==2){
					//this may happen since the view validates for 2 or 4 years digits
					$card_exp_year='20'.$card_exp_year;
				}	


				$x_exp_date=$card_exp_year.$card_exp_month;

				$card_data->setCardNumber($x_card_no);
				$card_data->setCardExpiryDate($x_exp_date);
				$card_data->setCvvData($x_card_cvv);
				$card_data->setBillingAddress1($order->billing_address_1);
				$card_data->setBillingAddress2($order->billing_address_2);
				$card_data->setBillingCity($order->billing_city);
				$card_data->setBillingState($order->billing_state);
				$card_data->setBillingZip($order->billing_postcode);
				// Customer data
				$customer = new Customer();
				$customer->setFirstName($order->billing_first_name);
				$customer->setLastName($order->billing_last_name);
				if ( $doNotSendCustomerEmailToPayHub == "no" ){
					$customer->setEmailAddress($order->billing_email);
				}
				$customer->setPhoneNumber(preg_replace('/[^0-9]/', '', $order->billing_phone));			
				$customer->setPhoneType("M");

				$object = new Sale($merchant,$customer,$bill,$card_data);
				//wc_add_notice(json_encode($object), 'error');
				$transaction = new TransactionManager($merchant,$WsURL,$oauth_token);
				$result = $transaction->doSale($object);
				//wc_add_notice(json_encode($result), 'error');


				$ph_transaction_id='';	
				if($result->getErrors()==null){

				    $ph_transaction_id = $result->getSaleResponse()->getSaleId();
					$order->add_order_note( __('Transaction completed', 'woothemes') . ' PayHub Transaction ID: ' . $ph_transaction_id);			
					$order->payment_complete($ph_transaction_id);
								
					// Remove cart
								
					//$woocommerce->cart->empty_cart();
					// Empty awaiting payment session
					unset($_SESSION);

					// Return thank you page redirect
					return array(
						'result' 	=> 'success',
						#'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id'))))
						'redirect' => $this->get_return_url($order)
						);
				}else{
					
					$ph_response_code = $result->getErrors()[0]->code;
					$ph_response_text = $result->getErrors()[0]->reason;
					$error_msg = __('Payment Error:  ', 'woothemes') . "$ph_response_text ( $ph_response_code )";
								
					# We support WC 2.x and
					# WooCommerce::add_error was removed in WC 2.3 and
					# wc_add_notice was added in WC 2.1
					if ($this->isWcVersionTwoPointOneOrGreater()) {
						wc_add_notice($error_msg, 'error');
					}
					else {
						$woocommerce->add_error($error_msg);
					}

					$order->update_status('failed');

					$order_note = 'PayHub ' . __('Transaction Failed:', 'woothemes') . "\n\n";
					$order_note .= "Response Code: $ph_response_code\n";
					$order_note .= "Response Text: $ph_response_text\n";
					
					$order->add_order_note($order_note);
								
					return;
				}
			}else{
				return;
			}
		}


		public function process_refund( $order_id, $amount = null,$reason = ''  ) {
		 	$mode = $this->demo;		
		 	$order = wc_get_order( $order_id );

		 	$transaction_id = get_post_meta($order_id, '_transaction_id', true);
			
			$merchant = new Merchant();
			
			if ($mode == "yes") {
				$WsURL="https://sandbox-api.payhub.com/api/v2/";
				$oauth_token = $this->api_password_demo;
				$merchant->setOrganizationId($this->org_id_demo);
				$merchant->setTerminalId($this->terminal_id_demo);
			} 
		    else {
				$WsURL="https://dc1-api.payhub.com/api/v2/";
				$oauth_token = $this->api_password;
				$merchant->setOrganizationId($this->orgid);
				$merchant->setTerminalId($this->tid);
			}

			$bill= new Bill();
			$bill->setBaseAmount($amount);			

			$bill->setNote($reason);
		
			$object = new Refund($transaction_id,$merchant,"CREDIT_CARD",$bill);
		    $transaction = new TransactionManager($merchant,$WsURL,$oauth_token);

		    $saleInfo = $transaction->getSaleInformation($transaction_id);

		    if($saleInfo->getErrors()==null){
		    		$status = $saleInfo->settlementStatus;
		    		$nose=json_encode($saleInfo);
		    		if($status=='Settled'){
		    			$result=$transaction->doRefund($object);				
						$ph_transaction_id='';	
						if($result->getErrors()==null){
						    $ph_transaction_id =$result->getLastRefundResponse()->getSaleTransactionId();
						    $ph_refund_id = $result->getLastRefundResponse()->getRefundTransactionId();
							$order->add_order_note( __('Transaction Refunded', 'woothemes') . ' PayHub Transaction ID: ' . $ph_transaction_id);
							$order->add_order_note( __('Transaction Refunded', 'woothemes') . ' PayHub Refund ID: ' . $ph_refund_id);		

							$order->update_status('Refunded');	
							return true;
						}else{
							
							$ph_response_code = $result->getErrors()[0]->code;
							$ph_response_text = $result->getErrors()[0]->reason;
							$error_msg = __('Payment Error:  ', 'woothemes') . "$ph_response_text ( $ph_response_code )";								
							return new WP_Error( 'error', __( 'Refund Failed: '.$error_msg, 'woocommerce' ) );				
						}
		    		}else{
		    			return new WP_Error( 'error', __( 'Refund Failed: Transaction has Not been Settled yet.', 'woocommerce' ) );
		    		}
		    		
		    }else{
		    	$ph_response_code = $saleInfo->getErrors()[0]->code;
				$ph_response_text = $saleInfo->getErrors()[0]->reason;
				$error_msg = __('Payment Error:  ', 'woothemes') . "$ph_response_text ( $ph_response_code )";								
				return new WP_Error( 'error', __( 'Refund Failed: '.$error_msg, 'woocommerce' ) );
		    }
		}
	}
}

function woocommerce_add_payhub_gateway( $methods ) {
	$methods[] = 'WC_PayHub_Gateway';
	return $methods;
}

add_filter('woocommerce_payment_gateways', 'woocommerce_add_payhub_gateway');	
add_action( 'admin_footer', 'payhub_javascript_functions' ); // Write our JS below here



/**
 * Validate the payment form
 */
function validate_fields() {		
	$card_number = str_replace( array(' ', '-' ), '', $_POST['payhub-card-number'] );
	$card_cvv=(isset($_POST['payhub-card-cvc'])) ? $_POST ['payhub-card-cvc'] : '';
	$x_exp_date_aux=isset($_POST['payhub-card-cvc']) ? explode ("/",$_POST['payhub-card-expiry']) :  array('','');
	$card_exp_month		=  str_replace( array(' ', '-' ), '',$x_exp_date_aux[0]);
	$card_exp_year 		=  str_replace( array(' ', '-' ), '',$x_exp_date_aux[1]);

	
	// Check card number
	if(empty($card_number) || !ctype_digit($card_number)) {
		wc_add_notice('Card number is required'.' '.$card_type , 'error');
		return false;
	}
	
	// Check card security code
	
	if(!ctype_digit($card_cvv)) {
		wc_add_notice('Card security code is invalid (only digits are allowed)', 'error');
		return false;
	}
	if(strlen($card_cvv) <3) {
		wc_add_notice('Card security code, invalid length', 'error');
		return false;
	}

	if(empty($card_exp_year)) {
		wc_add_notice('Card expiration year is required', 'error');
		return false;
	}else{
		if(strlen($card_exp_year)==1 ||strlen($card_exp_year)==3||strlen($card_exp_year)>4){
			wc_add_notice('Card expiration year is invalid', 'error');
			return false;
		}

		if(strlen($card_exp_year)==2){
			if((int)$card_exp_year < (int)substr(date('Y'), -2)) {
				wc_add_notice('Card expiration year is invalid 1', 'error');
				return false;
			}
		}

		if(strlen($card_exp_year)==4){
			if((int)$card_exp_year < (int)date('Y')) {
				wc_add_notice('Card expiration year is invalid', 'error');
				return false;
			}
		}
	}
	if(empty($card_exp_month)) {
		wc_add_notice('Card expiration mont is required','error');
		return false;
	}else{
		if((int)$card_exp_month>12 || (int)$card_exp_month<1) {
			wc_add_notice('Card expiration month is invalid', 'error');
			return false;
		}
	}

	

	//wc_add_notice('Card number is invalid', 'error');
	return true;
}



function payhub_javascript_functions() { 
	?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {
		jQuery("#woocommerce_payhub_testConnection").attr('value','Test your Connection (Not Tested)');
		jQuery("#woocommerce_payhub_testConnection").css({
						  'color':'#fff',
						  'background-color':'#31b0d5',
						  'border-color':'#269abc'
						});

		if(jQuery("#woocommerce_payhub_demo").is(':checked')){				
				enableDemo();			
	 		}else{
	 			disableDemo();
	 		}

	 	jQuery("#woocommerce_payhub_demo").on("change",function(){	 	
			if(jQuery("#woocommerce_payhub_demo").is(':checked')){
				enableDemo();
	 		}else{
	 			disableDemo();
	 		}
		});

		jQuery("#woocommerce_payhub_testConnection").on("click",function(){
			jQuery("#woocommerce_payhub_testConnection").attr('value','Connecting');
			jQuery("#woocommerce_payhub_testConnection").attr('style','');
			jQuery("#woocommerce_payhub_testConnection").css({
						  'color':'#fff',
						  'background-color':'#ec971f',
						  'border-color':'#d58512'
						});

			
			var datas = {
				mode:'live',
				token:'',
				org_id:'',
				terminal_id:''
			}
			if(jQuery("#woocommerce_payhub_demo").is(':checked')){
				datas.mode = 'demo';
				datas.org_id= jQuery("#woocommerce_payhub_org_id_demo").val();
				datas.terminal_id= jQuery("#woocommerce_payhub_terminal_id_demo").val();
				datas.token=jQuery("#woocommerce_payhub_api_password_demo").val();
	 		}else{
				datas.mode = 'live';
				datas.org_id= jQuery("#woocommerce_payhub_org_id").val();
				datas.terminal_id= jQuery("#woocommerce_payhub_terminal_id").val();
				datas.token=jQuery("#woocommerce_payhub_api_password").val();
	 		}
	 		url_action = "<?php echo admin_url('/admin-ajax.php'); ?>";
      		jQuery.ajax({
			  type:"POST",
			  url: url_action,
			  data: {
			      action: "payhub_test_connection",
			      data: datas
			  },
			  success:function(data){
			  	data=JSON.parse(data);
			  	if(data){
			  		
			  		jQuery("#woocommerce_payhub_testConnection").attr('style','');
			  		jQuery("#woocommerce_payhub_testConnection").css({
						  'color':'#fff',
						  'background-color':'#5cb85c',
						  'border-color':'#4cae4c'
						});

			  		jQuery("#woocommerce_payhub_testConnection").attr('value','Connected');
			  	}else{
			  		jQuery("#woocommerce_payhub_testConnection").attr('style','');
			  		jQuery("#woocommerce_payhub_testConnection").css({
						  "color":'#fff',
						  'background-color':'#d9534f',
						  'border-color':'#d43f3a'
						});
			  		jQuery("#woocommerce_payhub_testConnection").attr('value','Not Connected');
			  	}
			  },
			  error: function(errorThrown){
			    console.log(errorThrown);
			  } 

			});

			return false;
		});
	});


	function enableDemo(){
		jQuery("#woocommerce_payhub_api_password").parent().parent().parent().hide();
		jQuery("#woocommerce_payhub_orgid").parent().parent().parent().hide();
		jQuery("#woocommerce_payhub_terminal_id").parent().parent().parent().hide();

		jQuery("#woocommerce_payhub_api_password_demo").parent().parent().parent().show();
		jQuery("#woocommerce_payhub_org_id_demo").parent().parent().parent().show();
		jQuery("#woocommerce_payhub_terminal_id_demo").parent().parent().parent().show();
	}
	function disableDemo(){
		jQuery("#woocommerce_payhub_api_password").parent().parent().parent().show();
		jQuery("#woocommerce_payhub_orgid").parent().parent().parent().show();
		jQuery("#woocommerce_payhub_terminal_id").parent().parent().parent().show();

		jQuery("#woocommerce_payhub_api_password_demo").parent().parent().parent().hide();
		jQuery("#woocommerce_payhub_org_id_demo").parent().parent().parent().hide();
		jQuery("#woocommerce_payhub_terminal_id_demo").parent().parent().parent().hide();
	}
	</script> 
	<?php
}

add_action('wp_ajax_payhub_test_connection', 'payhub_test_connection');

function payhub_test_connection() {	
	$data = $_POST['data'];
		//Defining data for the SALE transaction
			// Merchant data (obtained from the payHub Virtual Terminal (3rd party integration)
		$merchant = new Merchant();

		if ($data['mode'] == "demo") {
			$WsURL="https://sandbox02-api.payhub.com/api/v2/";
		} 
	    else {
			$WsURL="https://dc1-api.payhub.com/api/v2/";
		}

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $WsURL,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_FOLLOWLOCATION => 1,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_SSL_VERIFYPEER=>0,
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$data['token'],
		    "cache-control: no-cache",
		    "content-type: application/json"
		  ),
		));

		$response = curl_exec($curl);
		$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	    curl_close($curl);
	  

	    if ($httpcode>=200 && $httpcode< 400){
	        echo json_encode(true);
	    } else {
	        echo json_encode(false);
	    }					
		wp_die();

}
