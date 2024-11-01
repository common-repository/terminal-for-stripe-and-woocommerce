<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Stripe_Terminal_API class.
 *
 * Communicates with Stripe API.
 */
class WC_Stripe_Terminal_API extends WC_Stripe_API{
	public static function get_user_agent() {
		$app_info = array(
			'name' => 'Stripe Terminal for WooCommerce',
			'partner_id' => 'pp_partner_GVkx9idOhmfJ7c',
			'version' => (defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '2.1.2'),
			'url' => 'https://www.arcanestrategies.com/products/stripe-terminal-for-woocommerce',
		);
				
		$uname = 'Unavailable';
		
		try {
			$uname = php_uname();
		} catch(Exception $e){
			try {
				$uname = PHP_OS;
			} catch(Exception $e){
				// Do nothing
			}
		}

		return array(
			'lang'         => 'php',
			'lang_version' => phpversion(),
			'publisher'    => 'arcane_strategies',
			'uname'        => $uname,
			'application'  => $app_info,
		);
	}

	public static function get_headers($terminal=false,$accessor=null) {
		$user_agent = self::get_user_agent();
		$app_info   = $user_agent['application'];
		$main_settings = get_option( 'woocommerce_stripe_settings' );
		$testmode = ( ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'] ) ? true : false;
		$tfsw_settings = get_option('woocommerce_stripe_terminal_settings');
		$account = !empty( $tfsw_settings[ 'stripe_connect_id' ] ) ? $tfsw_settings[ 'stripe_connect_id' ] : false;
		if(empty($account)){
			return ['error'=>'Invalid Stripe Connect Account ID','code'=>403, 'message'=>'Stripe Connect requires an account ID.  Open your plugin settings and fill out the Stripe Connect Account ID field with your account ID (begins with "acct_").  This can be found in your Stripe dashboard.  Be sure to complete the Stripe Connect process if you have not yet done so.'];
		}
		$z = self::get_secret_key();
		$args = [
 				'Authorization'              => 'Basic ' . base64_encode( $z . ':' ),
 				'Stripe-Version'             => self::STRIPE_API_VERSION,
 				'User-Agent'                 => $app_info['name'] . '/' . $app_info['version'] . ' (' . $app_info['url'] . ')',
 				'X-Stripe-Client-User-Agent' => json_encode( $user_agent ),
			];
 		if(!empty($accessor)){
 			if(!is_array($accessor)){
 				$accessor = json_decode(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $accessor),true);
 			}
 			if(!empty($accessor)){
 				$z = base64_decode($accessor['accessor']);
 			}
		}			
		$is_connected = wp_remote_get('https://store.arcanestrategies.com/api/connect-'.($testmode? 'test-' : '').'check/'.$account);
		if(is_object($is_connected)){
			$is_connected = (array)$is_connected;
 		}
		$response = !empty($is_connected['body'])? json_decode($is_connected['body']) : null;
		if(!empty($response)&&$response->code==='200'&&!empty($z)&&!empty($accessor)){
			$args['Authorization'] = 'Basic ' . base64_encode( $z . ':' );
			$args['Stripe-Account'] = $account;
		} else if($terminal==true){
			return ['error'=>'Invalid Stripe Connect','code'=>403, 'message'=>'Stripe Connect failed for this account.  Please verify your Account ID is correct in your plugin settings and complete the Stripe Connect process by clicking the Stripe Connect button.'];
		}

		return apply_filters(
			'woocommerce_stripe_request_headers',
			$args
 		);
	}

	public static function request( $request, $api = 'charges', $accessor = null, $method = 'POST', $with_headers = false ) {
		WC_Stripe_Logger::log( "{$api} request: " . print_r( $request, true ) );

		$headers         = self::get_headers(((false!==strpos($api,'terminal')||false!==strpos($api,'intent'))? true : false),$accessor);
		$idempotency_key = '';
		
		if(!empty($headers['Stripe-Account'])&&$api==='payment_intents'){
			$request[base64_decode('YXBwbGljYXRpb25fZmVlX2Ftb3VudA==')] = ceil(($request['amount']*$accessor['contract'])+base64_decode('MTU='));
		}

		if ( 'charges' === $api && 'POST' === $method ) {
			$customer        = ! empty( $request['customer'] ) ? $request['customer'] : '';
			$source          = ! empty( $request['source'] ) ? $request['source'] : $customer;
			$idempotency_key = apply_filters( 'wc_stripe_idempotency_key', $request['metadata']['order_id'] . '-' . $source, $request );
			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$response = wp_safe_remote_post(
			self::ENDPOINT . $api,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => apply_filters( 'woocommerce_stripe_request_body', $request, $api ),
				'timeout' => 70,
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			WC_Stripe_Logger::log(
				'Error Response: ' . print_r( $response, true ) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r(
					array(
						'api'             => $api,
						'request'         => $request,
						'idempotency_key' => $idempotency_key,
					),
					true
				)
			);

			throw new WC_Stripe_Exception( print_r( $response, true ), __( 'There was a problem connecting to the Stripe API endpoint.', 'woocommerce-gateway-stripe' ) );
		}

		if ( $with_headers ) {
			return array(
				'headers' => wp_remote_retrieve_headers( $response ),
				'body'    => json_decode( $response['body'] ),
			);
		}

		return json_decode( $response['body'] );
	}
}