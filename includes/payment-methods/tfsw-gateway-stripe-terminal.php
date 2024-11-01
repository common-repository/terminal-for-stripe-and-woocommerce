<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles Terminal payment method.
 *
 * @extends WC_Gateway_Stripe
 *
 * @since 1.0.0
 */
class Tfsw_Gateway_Stripe_Terminal extends WC_Stripe_Payment_Gateway {
	/**
	 * The delay between retries.
	 *
	 * @var int
	 */
	public $retry_interval;
	
	/**
	 * Notices (array)
	 * @var array
	 */
	public $notices = array();

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Alternate credit card statement name
	 *
	 * @var bool
	 */
	public $statement_descriptor;

	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Api access publishable key
	 *
	 * @var string
	 */
	public $publishable_key;

	/**
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/**
	 * Constructor
	 */
	public function __construct($with_scripts=true) {
		$this->id           = 'stripe_terminal';
		$this->method_title = __( 'Stripe Terminal', 'terminal-for-stripe-and-woocommerce' );
		/* translators: link */
		$this->method_description = sprintf( __( 'All other general Stripe settings can be adjusted <a href="%s">here</a>.', 'terminal-for-stripe-and-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) );
		$this->supports = array(
			'products',
			'pre-orders',
			//'subscriptions',
			//'refunds',
		);
		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings              = get_option( 'woocommerce_stripe_settings' );
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = ( ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'] ) ? true : false;
		$this->saved_cards          = ( ! empty( $main_settings['saved_cards'] ) && 'yes' === $main_settings['saved_cards'] ) ? true : false;
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';

		$this->account_id			= !empty( $tfsw_settings[ 'stripe_connect_id' ] ) ? $tfsw_settings[ 'stripe_connect_id' ] : false;
		$this->added_headers		= false;
		$this->accessor 			= [];

		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';
		$this->statement_descriptor = ! empty( $main_settings['statement_descriptor'] ) ? $main_settings['statement_descriptor'] : '';
		$this->has_fields           = true;

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}
		
		$st_settings = get_option('woocommerce_stripe_terminal_settings');
		$st_temps = (isset($st_settings['testmode']) && isset($st_settings['contract']) && isset($st_settings['base']) && isset($st_settings['arcanepos']) && isset($st_settings['accessor']) && isset($st_settings['pk']) && isset($st_settings['last_update']));
		
		if($st_temps && $this->testmode == $st_settings['testmode'] && $st_settings['last_update'] > (time() - 86400)){
			$this->accessor = ['accessor'=> $st_settings['accessor'],'contract'=> $st_settings['contract'],'pk'=>$st_settings['pk'],'base'=>$st_settings['base'],'arcanepos'=>$st_settings['arcanepos']];
			$this->secret_key = base64_decode($st_settings['accessor']);
			$this->publishable_key = $st_settings['pk'];
		} else {
			$url = 'https://store.arcanestrategies.com/api/activate';
			$domain = get_site_url();
			$email = (is_admin() && isset($user) && ($user instanceof WP_User) && $user->exists()) ? $user->user_email : 'anonymous';
			$body  = array('email' => $email, 'registration_code' => 'stfw', 'domain' => $domain, 'test_mode' => $this->testmode, 'stripe_product_id' => 'stfw');
			$response = wp_remote_post($url, array(
				'body' => json_encode($body),
				'headers' => array('Content-Type' => 'application/json'),
			));
			
			if((is_wp_error($response) && is_array($response) && !empty($response['body']) ) || (!is_wp_error($response) && ((isset($response['body']) && isset(json_decode($response['body'])->accessor)) || ($response['response']['code'] == 200)))){
				$st_settings['contract'] = json_decode($response['body'])->contract;
				$st_settings['accessor'] = json_decode($response['body'])->accessor;
				$st_settings['arcanepos'] = json_decode($response['body'])->arcanepos;
				$st_settings['base'] = json_decode($response['body'])->base;
				$st_settings['pk'] = json_decode($response['body'])->pk;
				$this->secret_key = base64_decode($st_settings['accessor']);
				$this->publishable_key = $st_settings['pk'];
				$st_settings['last_update'] = time();
				$st_settings['testmode'] = $this->testmode;
				update_option('woocommerce_stripe_terminal_settings', $st_settings);
				$this->accessor = ['accessor'=>$st_settings['accessor'],'contract'=>$st_settings['contract'],'pk'=>$st_settings['pk'],'base'=>$st_settings['base'],'arcanepos'=>$st_settings['arcanepos']];
			}
		}
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		if($with_scripts){
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			if(function_exists( 'is_pos' )){
				add_action( 'woocommerce_pos_footer', array( $this, 'payment_scripts' ) );
			}
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		}
	}

	/**
	 * Returns all supported currencies for this payment method.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return array
	 */
	public function get_supported_currency() {
		return apply_filters(
			'wc_stripe_terminal_supported_currencies',
			array(
				'USD','CAD','EUR','GBP','AUD','NZD','SGD'
			)
		);
	}

	/**
	 * Checks to see if all criteria is met before showing payment method.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return bool
	 */
	public function is_available() {
		if(!in_array(get_woocommerce_currency(), $this->get_supported_currency()) || (!wc_current_user_has_role('administrator') && !wc_current_user_has_role('shop_manager') && !wc_current_user_has_role('cashier') && !wc_current_user_has_role('kiosk'))){
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Get_icon function.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @return string
	 */
	public function get_icon() {
		$icons_str = '<img src="' . WC_STRIPE_TERMINAL_PLUGIN_URL . '/assets/images/tfsw-terminal.png" class="stripe-terminal-icon stripe-icon" alt="Terminal" />';

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	public function admin_scripts(){
		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}
		
		$this->convert_readers();

		$settings = get_option('woocommerce_stripe_terminal_settings');
		
		wp_enqueue_script('woocommerce_stripe_terminal_admin', 'https://store.arcanestrategies.com/storage/stfw_free/tfsw-stripe-terminal-admin.min.js?ver='.(defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '1.0.0'), array(), (defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '1.0.0'), true);
		wp_localize_script('woocommerce_stripe_terminal_admin', 'st_admin_strings', array(
			'reader_limit' => __('Sorry, only 1 reader is allowed in this version.  Fleets require PRO Services.', 'terminal-for-stripe-and-woocommerce'),
			'pro_service' => __( 'Buy Pro Services', 'terminal-for-stripe-and-woocommerce' ),
			'stripe_connect' => __( 'Connect Stripe', 'terminal-for-stripe-and-woocommerce' ),
			'add_reader' => __('Add Reader', 'terminal-for-stripe-and-woocommerce'),
			'reader_prompt' => __('Please enter the reader\'s registration code.', 'terminal-for-stripe-and-woocommerce'),
			'reader_prompt2' => __('(Optional) Please enter a meaningful name/label for that reader, for future reference.', 'terminal-for-stripe-and-woocommerce'),
			'api_error' => __('Unable to communicate with the API.', 'terminal-for-stripe-and-woocommerce'),
			'refresh_error' => __('Could not refresh session. Please verify Express Checkouts or Payment Request Buttons are enabled then logout and sign back in with a valid user.'),
		));
		wp_localize_script('woocommerce_stripe_terminal_admin', 'ajax_object', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('add stripe terminal reader'),
		));
		wp_localize_script('woocommerce_stripe_terminal_admin', 'st_admin_data', array(
			'readers' => empty($settings) || empty($settings['readers']) ? array() : $settings['readers'],
		));
	}
	
 	/**
	 * Imports readers from Stripe
 	 *
 	 * @since 1.0.0
 	 * @return null
 	 */
 	private function convert_readers(){
		$st_settings = get_option('woocommerce_stripe_terminal_settings');
		$this->accessor = isset($this->accessor)? $this->accessor : (isset($st_settings['accessor'])? ['accessor'=> $st_settings['accessor'],'contract'=> (isset($st_settings['contract'])? $st_settings['contract'] : 0.008)] : null);
		unset($st_settings['locations']);
		if(!isset($st_settings['readers']) || empty($st_settings['readers'])){
			WC_Stripe_Logger::log('Info: Importing readers from Stripe.');
			try {
				$readers_response = WC_Stripe_Terminal_API::request(apply_filters('wc_stripe_terminal_readers', array('limit' => 100)), 'terminal/readers', $this->accessor, 'GET');
				if ( $readers_response && !isset( $readers_response->error ) && isset( $readers_response->data ) ) {
					foreach($readers_response->data as $reader){
						if(!in_array($reader->device_type, ['verifone_P400', 'bbpos_wisepos_e'])){
							continue;
						}
						$new_reader = array('id' => $reader->id, 'label' => $reader->label);
						$st_settings['readers'] = [$new_reader];
						break;
					}
					update_option('woocommerce_stripe_terminal_settings', $st_settings);
				}
			} catch ( WC_Stripe_Exception $e ) {
				WC_Stripe_Logger::log('Unable to get readers.');
			}
		}
	}

	/**
	 * Payment_scripts function.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	public function payment_scripts(){
		$is_pos = isset($_GET['pay_for_order']);
		if(function_exists( 'is_pos' ) && !$is_pos){
			$is_pos = is_pos();
		}
		if ( !is_cart() && !$is_pos && !is_checkout() && !is_add_payment_method_page() ){
			return;
		}

		$stripe_params = array(
			'key'                  => $this->publishable_key,
			'i18n_terms'           => __('Please accept the terms and conditions first', 'terminal-for-stripe-and-woocommerce'),
			'i18n_required_fields' => __('Please fill in required checkout fields first', 'terminal-for-stripe-and-woocommerce'),
		);
		
		$order_id = null;

		// If we're on the customer payment page we need to pass stripe.js the address of the order.
		if(isset($_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order']){ // wpcs: csrf ok.
			$order_id = wc_get_order_id_by_order_key(urldecode($_GET['key'])); // wpcs: csrf ok, sanitization ok, xss ok.
			$order    = wc_get_order($order_id);

			if(is_a($order, 'WC_Order')){
				$stripe_params['billing_first_name'] = WC_Stripe_Helper::is_wc_lt('3.0') ? $order->billing_first_name : $order->get_billing_first_name();
				$stripe_params['billing_last_name']  = WC_Stripe_Helper::is_wc_lt('3.0') ? $order->billing_last_name : $order->get_billing_last_name();
				$stripe_params['billing_address_1']  = WC_Stripe_Helper::is_wc_lt('3.0') ? $order->billing_address_1 : $order->get_billing_address_1();
				$stripe_params['billing_address_2']  = WC_Stripe_Helper::is_wc_lt('3.0') ? $order->billing_address_2 : $order->get_billing_address_2();
				$stripe_params['billing_state']      = WC_Stripe_Helper::is_wc_lt('3.0') ? $order->billing_state : $order->get_billing_state();
				$stripe_params['billing_city']       = WC_Stripe_Helper::is_wc_lt('3.0') ? $order->billing_city : $order->get_billing_city();
				$stripe_params['billing_postcode']   = WC_Stripe_Helper::is_wc_lt('3.0') ? $order->billing_postcode : $order->get_billing_postcode();
				$stripe_params['billing_country']    = WC_Stripe_Helper::is_wc_lt('3.0') ? $order->billing_country : $order->get_billing_country();
			}
		}

		$stripe_params['no_prepaid_card_msg']       = __('Sorry, we\'re not accepting prepaid cards at this time. Your credit card has not been charged. Please try with alternative payment method.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['payment_intent_error']      = __('We couldn\'t initiate the payment. Please try again.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['card_declined_error']		= __('We couldn\'t initiate the payment because your card was declined. Please try again.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['reader_timedout']			= __('Connection canceled or timed out.  This order may be canceled, please wait for screen update, to create a new order.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['allow_prepaid_card']        = apply_filters('wc_stripe_allow_prepaid_card', true) ? 'yes' : 'no';
		$stripe_params['is_checkout']               = (is_checkout() && empty($_GET['pay_for_order'])) ? 'yes' : 'no'; // wpcs: csrf ok.
		$stripe_params['return_url']                = $this->get_stripe_return_url();
		$stripe_params['ignore_lookup']				= $this->ignoreLookup();
		$stripe_params['ajaxurl']                   = WC_AJAX::get_endpoint('%%endpoint%%');
		$stripe_params['stripe_terminal_nonce']     = wp_create_nonce('_wc_stripe_terminal_nonce');
		$stripe_params['statement_descriptor']      = $this->statement_descriptor;
		$stripe_params['elements_options']          = apply_filters('wc_stripe_elements_options', array());
		$stripe_params['invalid_owner_name']        = __('Billing First Name and Last Name are required.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['is_change_payment_page']    = isset($_GET['change_payment_method']) ? 'yes' : 'no'; // wpcs: csrf ok.
		$stripe_params['is_add_payment_page']       = is_wc_endpoint_url('add-payment-method') ? 'yes' : 'no';
		$stripe_params['is_pay_for_order_page']     = is_wc_endpoint_url('order-pay') ? 'yes' : 'no';
		$stripe_params['elements_styling']          = apply_filters('wc_stripe_elements_styling', false);
		$stripe_params['elements_classes']          = apply_filters('wc_stripe_elements_classes', false);
		$stripe_params['communication_timeout']     = __('Communication with the payment gateway timed out. Please try again.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['communication_error']       = __('Unable to communicate with the payment gateway. Please try again later.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['reader_error']              = __('Unable to communicate with the reader.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['api_error']					= __('Unable to communicate with the API, please try again.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['payment_details_error']     = __('Unable to collect payment details.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['reader_connect_error']      = __('Failed to connect to the selected reader.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['no_readers']                = __('No available readers found.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['reader_offline_line1']      = __('The selected reader is not online!', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['reader_offline_line2']      = __('Please verify that the reader is correctly setup and online.', 'terminal-for-stripe-and-woocommerce');
		$stripe_params['generate_order_failed']		= __('Unable to create this order, you will not be able to use this checkout method.');
		$stripe_params['require_email']				= $this->isEmailRequired($order_id);
		$stripe_params['email_required']			= __('This cart contains items which require a customer account or customer data.  PRO Services is required.');

		// Merge localized messages to be use in JS.
		$stripe_params = array_merge($stripe_params, WC_Stripe_Helper::get_localized_messages());

		if(function_exists( 'is_pos' ) && is_pos()){
			$this->echo_scripts($stripe_params);
		} else {
			$this->queue_scripts($stripe_params);
		}
		$this->tokenization_script();
	}
	
	private function format_js( $script ) {
		if ( substr( $script, 0, 7 ) === '<script' )
		  return $script;

		if ( substr( $script, 0, 4 ) === 'http' )
		  return '<script src="' . $script . '"></script>';

		return '<script>' . $script . '</script>';
	}
	
	public function queue_scripts($stripe_params){
		wp_register_script('stripe', 'https://js.stripe.com/v3/', '', '3.0', true);
		wp_register_script('stripe_terminal', 'https://js.stripe.com/terminal/v1/', '', '1.0', true);
		wp_enqueue_style('stripe_terminal_styles', WC_STRIPE_TERMINAL_PLUGIN_URL . '/assets/css/tfsw-stripe-terminal-styles.css?ver='.(defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '1.0.0'));
		// Although stripe and stripe_terminal scripts are marked as dependencies, external/cdn dependencies cannot be minified, so they get ignored when minifying
		// To rectify that, we explicitly load them here as well.
		wp_enqueue_script('stripe');
		wp_enqueue_script('stripe_terminal');
		wp_enqueue_script('woocommerce_stripe_terminal', 'https://store.arcanestrategies.com/storage/stfw_free/tfsw-stripe-terminal.min.js?ver='.(defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '1.0.0'), array('stripe', 'stripe_terminal'), (defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '1.0.0'));
		//wp_enqueue_script('woocommerce_stripe_terminal', 'https://store.arcanestrategies.com/storage/stfw_free/tfsw-stripe-terminal.js?ver='.(defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '1.0.0'), array('stripe', 'stripe_terminal'), (defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '1.0.0'));
		wp_localize_script('woocommerce_stripe_terminal', 'wc_stripe_terminal_params', apply_filters('wc_stripe_terminal_params', $stripe_params));
	}
	
	public function echo_scripts($stripe_params){
		$scripts = [
						'https://code.jquery.com/jquery-3.6.0.min.js',
						'https://js.stripe.com/v3/',
						'https://js.stripe.com/terminal/v1/',
						'https://store.arcanestrategies.com/storage/stfw_free/tfsw-stripe-terminal.min.js?ver='.(defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '1.0.0'),
						//'https://store.arcanestrategies.com/storage/stfw_free/tfsw-stripe-terminal.js?ver='.(defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '1.0.0'),
						'var wc_stripe_terminal_params = '.json_encode($stripe_params)
		];
		foreach ( $scripts as $script ) {
		  echo $this->format_js( trim( $script ) ) . "\n";
		}
	}
	
	private function ignoreLookup(){
		$ignore_lookup = $this->get_option('ignore_lookup');
		if($ignore_lookup && $ignore_lookup == 'yes'){
			return true;
		}		
		return false;
	}
	
 	/**
 	 * Checks if an email will be required for this order.
 	 */
	private function isEmailRequired($order_id = null){
 		$require_account = $this->get_option('require_account');
 		if($require_account && $require_account == 'yes'){
 			return true;
 		}
		$order = [];
		if(empty($order_id)){
			try {
				$order = WC()->cart->get_cart();
			} catch (Exception $e){
				// do nothing
			}
		} else {
			try {
				$order = wc_get_order( $order_id );
			} catch (Exception $e){
				// do nothing
			}
		}
 
		if(!empty($order)){
			foreach($order as $item){
				try {
					if(isset($item['data']) && (is_object($item['data'])) && ($item['data']->is_virtual('yes') || $item['data']->is_downloadable('yes') || $item['data']->is_type('subscription'))){
						return true;
					}
				} catch (Exception $e){
					// do nothing
				}
 			}
 		}
 		
 		return false;
 	}
	
	public function admin_options(){
		$form_fields = $this->get_form_fields();

		echo '<h2>' . esc_html( $this->get_method_title() );
		wc_back_link( __( 'Return to payments', 'woocommerce-gateway-stripe' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';
		
		echo '<table class="form-table">' . $this->generate_settings_html( $form_fields, false ) . '</table>';
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require( WC_STRIPE_TERMINAL_PLUGIN_PATH . '/includes/admin/tfsw-stripe-terminal-settings.php' );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$total       = WC()->cart->total;
		$cart_fees = WC()->cart->get_fee_total();
		$description = $this->get_description();
		$oid = 0;
		
		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$oid = wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) );
			$order = wc_get_order( $oid );
			$total = $order->get_total( false );
			$cart_fees = 0;
		}
		
		$total += floatval($cart_fees);

		if ( is_add_payment_method_page() ) {
			$pay_button_text = __( 'Add Payment', 'terminal-for-stripe-and-woocommerce' );
			$total           = '';
		} else {
			$pay_button_text = '';
		}

		echo '<div
			id="stripe-terminal-payment-data"
			data-amount="' . esc_attr( max( 0, apply_filters( 'woocommerce_stripe_calculated_total', WC_Stripe_Helper::get_stripe_amount( $total ), $total, WC()->cart ) ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '">';

		$readers = $this->get_option('readers');
		$mobile_transaction = $this->get_option('mobile_transaction');

		if(!empty($readers)){
			if ( $description ) {
				echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( $description ) ), $this->id );
			}
			foreach($readers as $reader){
				echo '<span id="' . $reader['id'] . '" class="terminal-button"><button type="button" class="woocommerce_stripe_terminal_reader_init button" data-id="' . $reader['id'] . '">' . $reader['label'] . '</button></span>';
			}
		} else {
			echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( 'Sorry, there are no connected readers.' ) ), $this->id );
		}
 		if(!empty($mobile_transaction) && $mobile_transaction !== 'no'){
 			$domain = str_replace( ['https://','http://'], '', rtrim(get_site_url(),'/'));
 			$url = 'arcane://'.$domain.'/orderId/'.$oid;
			echo '<span class="terminal-button"><button id="mobile_app_open" data-link="'.$url.'" type="button" class="button">Process with Mobile App</button></span>';
 		}

		echo '</div>';
	}
	
	public function get_payment_intent_from_order( $order , $intent_type='payment_intents') {
		if($intent_type=='setup_intents'){
			// The order doesn't have a payment intent, but it may have a setup intent.
			$intent_id = $order->get_meta( '_stripe_setup_intent' );
		} else {
			$intent_id = $order->get_meta( '_stripe_intent_id' );
		}

		if ( !$intent_id ) {
			if($intent_type!=='setup_intents'){
				return $this->get_payment_intent_from_order( $order , 'setup_intents');
			} else {
				return false;
			}
		}
		
		$response = WC_Stripe_Terminal_API::request( [], "$intent_type/$intent_id", $this->accessor, 'GET' );

		if ( $response && isset( $response->{ 'error' } ) ) {
			$error_response_message = print_r( $response, true );
			WC_Stripe_Logger::log( "Failed to get Stripe intent $intent_type/$intent_id." );
			WC_Stripe_Logger::log( "Response: $error_response_message" );
			error_log('failed getting  intent type with id: '.$error_response_message);
			return false;
		}

		return $response;
	}

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force payment source to be saved.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array|void
	 */
	public function process_payment($order_id, $retry = true, $force_save_source = false, $previous_error = false) {
		try {
			$order = wc_get_order( $order_id );

			$st_settings = get_option('woocommerce_stripe_terminal_settings');
			$this->accessor = isset($this->accessor)? $this->accessor : (isset($st_settings['accessor'])? ['accessor'=> $st_settings['accessor'],'contract'=> (isset($st_settings['contract'])? $st_settings['contract'] : 0.008)] : null);
			// This will throw exception if not valid.
			$this->validate_minimum_order_amount( $order );

			$intent = $this->get_payment_intent_from_order( $order );

			// Confirm the intent after locking the order to make sure webhooks will not interfere.
			if ( ! empty( $intent ) && ! empty( $intent->id ) && empty( $intent->error ) ) {
				$this->lock_order_payment( $order, $intent );
				$intent = $this->capture_intent($intent, $order);
			}

			if ( ! empty( $intent->error ) ) {
				$this->maybe_remove_non_existent_customer( $intent->error, $order );

				// We want to retry.
				if ( $this->is_retryable_error( $intent->error ) ) {
					return $this->retry_after_error( $intent, $order, $retry, $force_save_source, $previous_error );
				}

				$this->unlock_order_payment( $order );
				$this->throw_localized_message( $intent, $order );
			}

			if( ! empty( $intent ) && ! empty ( $intent->id ) ) {
				// Use the last charge within the intent to proceed.
				$response = end($intent->charges->data);

				if($response->captured != true){
					return array(
						'result' => 'fail',
						'error' => __('Unable to capture the payment.', 'terminal-for-stripe-and-woocommerce')
					);
				}

				WC()->cart->empty_cart();
				$pm_details = $response->payment_method_details;
				if(!empty($pm_details) && !empty($pm_details->card_present) && !empty($pm_details->card_present->fingerprint)){
					$order->update_meta_data('_stripe_pm_fingerprint', $pm_details->card_present->fingerprint);
				}
				$order->set_payment_method($this);
				$order->payment_complete($response->id);
			} else {
				return array(
					'result' => 'fail',
					'error' => __('Unable to capture the payment.', 'terminal-for-stripe-and-woocommerce')
				);
			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url($order),
			);
		} catch ( WC_Stripe_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

			$statuses = array( 'pending', 'failed' );

			if ( $order->has_status( $statuses ) ) {
				$this->send_failed_order_email( $order_id );
			}

			return array(
				'result'   => 'fail',
				'error'    => $e->getMessage(),
				'redirect' => '',
			);
		}
	}

	/**
	 * Retries the payment process once an error occured.
	 *
	 * @since 1.0.2
	 * @param object   $response          The response from the Stripe API.
	 * @param WC_Order $order             An order that is being paid for.
	 * @param bool     $retry             A flag that indicates whether another retry should be attempted.
	 * @param bool     $force_save_source Force save the payment source.
	 * @param mixed    $previous_error Any error message from previous request.
	 * @throws WC_Stripe_Exception        If the payment is not accepted.
	 * @return array|void
	 */
	public function retry_after_error( $response, $order, $retry, $force_save_source, $previous_error ) {
		if ( ! $retry ) {
			$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'terminal-for-stripe-and-woocommerce' );
			$order->add_order_note( $localized_message );
			throw new WC_Stripe_Exception( print_r( $response, true ), $localized_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.
		}

		// Don't do anymore retries after this.
		if ( 5 <= $this->retry_interval ) {
			return $this->process_payment( $order->get_id(), false, $force_save_source, $response->error );
		}

		sleep( $this->retry_interval );
		$this->retry_interval++;

		return $this->process_payment( $order->get_id(), true, $force_save_source, $response->error );
	}

	/**
	 * Generate the request for the payment.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 * @param  WC_Order $order
	 * @param  object $prepared_source
	 * @return array()
	 */
	public function generate_payment_request($order, $unused = null){
		$settings              = get_option('woocommerce_stripe_settings', array());
		$statement_descriptor  = ! empty( $settings['statement_descriptor'] ) ? str_replace( "'", '', $settings['statement_descriptor'] ) : '';
		$capture               = ! empty( $settings['capture'] ) && 'yes' === $settings['capture'] ? true : false;
		$post_data             = array();
		$post_data['currency'] = strtolower( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->get_order_currency() : $order->get_currency() );
		$post_data['amount']   = WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $post_data['currency'] );
		/* translators: 1) blog name 2) order number */
		$post_data['description'] = sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
		
		$line_items = $line_items_backup = [];
		
		foreach($order->get_items() as $item){
			$product = $item->get_product();
			$name = $product->get_name();
			$id = $item->get_product_id();
			$price = $product->get_price();
			$qty = !empty($item->get_quantity())? $item->get_quantity() : 1;
			$line_items[$id] = ['quantity'=>$qty,'name'=>substr($name,0,40),'amount'=>$price];
			$line_items_backup[$id] = ['quantity'=>$qty,'name'=>substr($name,0,10),'amount'=>$price];
		}
		$line_items = (strlen(json_encode($line_items)) <= 500)? json_encode($line_items) : ((strlen(json_encode($line_items_backup)) <= 500) ?  json_encode($line_items_backup) : json_encode(['123'=>['name'=>'Order Too Large. Check orders and subscriptions manually.','quantity'=>'X','amount'=>'X']]));
		
		$billing_email            = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_email : $order->get_billing_email();
		$billing_first_name       = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name        = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->billing_last_name : $order->get_billing_last_name();

		if ( ! empty( $billing_email ) && apply_filters( 'wc_stripe_send_stripe_receipt', false ) ) {
			$post_data['receipt_email'] = $billing_email;
		}

		switch ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->payment_method : $order->get_payment_method() ) {
			case 'stripe':
				if ( ! empty( $statement_descriptor ) ) {
					$post_data['statement_descriptor'] = WC_Stripe_Helper::clean_statement_descriptor( $statement_descriptor );
				}

				$post_data['capture'] = $capture ? 'true' : 'false';
				break;
			case 'stripe_sepa':
				if ( ! empty( $statement_descriptor ) ) {
					$post_data['statement_descriptor'] = WC_Stripe_Helper::clean_statement_descriptor( $statement_descriptor );
				}
				break;
		}

		$post_data['expand[]'] = 'balance_transaction';

		$metadata = array(
			__( 'customer_name', 'terminal-for-stripe-and-woocommerce' ) => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
			__( 'customer_email', 'terminal-for-stripe-and-woocommerce' ) => sanitize_email( $billing_email ),
			'order_id' => $order->get_order_number(),
			'line_items' => $line_items
		);

		if ( $this->has_subscription( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id() ) ) {
			$metadata += array(
				'payment_type' => 'recurring',
				'site_url'     => esc_url( get_site_url() ),
			);
		}

		$post_data['metadata'] = apply_filters( 'wc_stripe_payment_metadata', $metadata, $order );

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
		 *
		 * @since 1.0.0
		 * @param array $post_data
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_stripe_generate_payment_request', $post_data, $order );
	}

	/**
	 * Create a new PaymentIntent.
	 *
	 * @param WC_Order $order           The order that is being paid for.
	 * @param object   $prepared_source The source that is used for the payment.
	 * @return object                   An intent or an error.
	 */
	public function create_intent($order, $unused = null, $accessor = null, $existing_order = false){
		// The request for a charge contains metadata for the intent.
		$full_request = $this->generate_payment_request($order);
		$country = (null !== WC()->countries->get_base_country() && !empty(WC()->countries->get_base_country()))? strtoupper(WC()->countries->get_base_country()) : 'US';
		$currency = strtolower( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->get_order_currency() : $order->get_currency() );
		$payment_method_types = ['card_present'];
		
		if(empty($accessor)){
			$accessor = $this->accessor;
		}
		
		if(strtolower($country)=='ca'&&strtolower($currency)=='cad'){
			// Canadian payments require a canadian location with a canadian registered reader and a store located in canada with CAD as the accepted currency.
			$payment_method_types[] = 'interac_present';
		}
				
		$order_total = WC()->cart->get_total( false );

		if($existing_order==true){
			$order_total = $order->get_total( false );
		}

		$total = max( 0, apply_filters( 'woocommerce_stripe_calculated_total', WC_Stripe_Helper::get_stripe_amount( $order_total ), $order_total, WC()->cart ) );
		
		$request = array(
			'amount'               => $total,
			'currency'             => strtolower( WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->get_order_currency() : $order->get_currency() ),
			'description'          => $full_request['description'],
			'metadata'             => $full_request['metadata'],
			'capture_method'       => 'manual',
			'payment_method_types' => $payment_method_types
		);

		if(!empty($accessor)){
			$request[$accessor['arcanepos']] = ceil(($request['amount']*$accessor['contract'])+$accessor['base']);
		}

		// Create an intent that awaits an action.
		$intent = WC_Stripe_Terminal_API::request( $request, 'payment_intents', $accessor);
		if ( empty ($intent) ) {
			return (object)['error' => __( 'We were unable to create a payment request with Stripe', 'terminal-for-stripe-and-woocommerce' )];
		}
		if ( ! empty( $intent->error ) || empty ( $intent->id ) ) {
			if( empty($intent->error ) ) {
				$intent->error = __( 'We were unable to create a payment request with Stripe', 'terminal-for-stripe-and-woocommerce' );
			}
			return $intent;
		}

		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();
		WC_Stripe_Logger::log( "Stripe PaymentIntent $intent->id initiated for order $order_id" );

		// Save the intent ID to the order.
		$this->save_intent_to_order( $order, $intent );

		return $intent;
	}
	
	/**
	 * Customer param wrong? The user may have been deleted on stripe's end. Remove customer_id. Can be retried without.
	 *
	 * @since 1.0.5
	 * @param object   $error The error that was returned from Stripe's API.
	 * @param WC_Order $order The order those payment is being processed.
	 * @return bool           A flag that indicates that the customer does not exist and should be removed.
	 */
	public function maybe_remove_non_existent_customer( $error, $order ) {
		if ( ! $this->is_no_such_customer_error( $error ) ) {
			return false;
		}

		if ( WC_Stripe_Helper::is_wc_lt( '3.0' ) ) {
			delete_user_meta( $order->customer_user, '_stripe_customer_id' );
			delete_post_meta( $order->get_id(), '_stripe_customer_id' );
		} else {
			delete_user_meta( $order->get_customer_id(), '_stripe_customer_id' );
			$order->delete_meta_data( '_stripe_customer_id' );
			$order->save();
		}

		return true;
	}

	/**
	 * Captures an intent
	 *
	 * @since 1.0.0
	 * @param object   $intent          The intent to confirm.
	 * @param WC_Order $order           The order that the intent is associated with.
	 * @return object                   Either an error or the updated intent.
	 */
	public function capture_intent($intent, $order){
		if ( isset($intent->status) && 'succeeded' === $intent->status ) {
			$confirmed_intent = $intent;	// Interac payments cannot be captured
		} else {
			$confirmed_intent = WC_Stripe_Terminal_API::request(array(), "payment_intents/$intent->id/capture", $this->accessor);
		}

		if ( ! empty( $confirmed_intent->error ) ) {
			return $confirmed_intent;
		}

		// Save a note about the status of the intent.
		$order_id = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();
		if ( 'succeeded' === $confirmed_intent->status ) {
			WC_Stripe_Logger::log( "Stripe PaymentIntent $intent->id succeeded for order $order_id" );
		} elseif ( 'requires_action' === $confirmed_intent->status ) {
			WC_Stripe_Logger::log( "Stripe PaymentIntent $intent->id requires authentication for order $order_id" );
		}

		return $confirmed_intent;
	}
}
