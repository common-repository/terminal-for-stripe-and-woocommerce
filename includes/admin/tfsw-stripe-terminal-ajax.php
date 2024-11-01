<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tfsw_Stripe_Terminal_Ajax extends WC_Stripe_Payment_Request{
    public function init(){
		$tfsw_class = new Tfsw_Gateway_Stripe_Terminal(false);
		$this->accessor = $tfsw_class->accessor;
		
        add_action('wp_ajax_add_terminal_reader', array($this, 'add_terminal_reader'));
        add_action('wp_ajax_delete_terminal_reader', array($this, 'delete_terminal_reader'));
        add_action('wc_ajax_wc_stripe_terminal_authenticate', array($this, 'authenticate_terminal'));
        add_action('wc_ajax_wc_stripe_terminal_get_cart_details', array($this, 'ajax_get_cart_details'));
		add_action('wc_ajax_wc_stripe_terminal_generate_order_from_cart', array($this, 'generate_order_from_cart'));
        add_action('wc_ajax_wc_stripe_terminal_create_payment_intent', array($this, 'create_payment_intent'));
        add_action('wc_ajax_wc_stripe_terminal_recreate_payment_intent', array($this, 'recreate_payment_intent'));
        add_action('wc_ajax_wc_stripe_terminal_cancel_payment_intent', array($this, 'cancel_payment_intent'));
        add_action('wc_ajax_wc_stripe_terminal_capture_payment_intent', array($this, 'capture_payment_intent'));
        add_action('wc_ajax_wc_stripe_terminal_cancel_order', array($this, 'cancel_order'));
        add_action('wp_ajax_add_or_remove_subscription', array($this, 'add_or_remove_subscription'));
    }
	
	/**
	 * Generate_order_from_cart function.
	 *
	 * @since 2.0.0
	 * @version 2.2.3
	 * @return json
	 */	
	public function generate_order_from_cart(){
		try {
			$cart = WC()->cart;
			$checkout = WC()->checkout();
			if(isset($_POST['id'])){
				$order_id = $_POST['id'];
				unset($_POST['id']);
			}
			$data = [];
			foreach($_POST as $key=>$value){
				if(false!==strpos($key,'billing') || false!==strpos($key,'shipping')){
					$data[$key] = sanitize_text_field($value);
				}
			}
			if(isset($_POST['line_items'])){
				/*$data['payment_method'] = 'test';
				$data['payment_method_title'] = 'test';
				$data['set_paid'] = false;*/
				$props = [
							'meta_data'=>['id','key','value'],
							'line_items'=>['id','name','product_id','variation_id','quantity','tax_class','subtotal','subtotal_tax','total','total_tax','taxes','meta_data','sku','price'],
							'fee_lines'=>['id','name','tax_class','tax_states','total','total_tax','taxes','meta_data'],
							'tax_lines'=>['id','rate_code','rate_id','label','compound','tax_total','shipping_tax_total','meta_data'],
							'shipping_lines'=>['id','method_title','method_id','total','total_tax','taxes','meta_data'],
							'coupon_lines'=>['id','code','discount','discount_tax','meta_data'],
							'refunds'=>['id','reason','total'],
							'taxes'=>['id','rate_code','rate_id','label','compound','tax_total','shipping_tax_total','meta_data']
						];
				foreach($_POST as $key=>$value){
					if(isset($props[$key])){
						if((!isset($data[$key]) || !empty($value)) && (is_array($value)||is_object($value))){
							$data[$key] = [];
							foreach($value as $skey => $svalue){
								if(is_numeric($skey)){
									$data[$key][$skey] = [];
									foreach($svalue as $tkey => $tvalue){
										if(in_array($tkey,$props[$key])){
											$data[$key][$skey][$tkey] = $tvalue;
										}
									}
								} else {
									if(in_array($skey,$props[$key])){
										$data[$key][$skey] = $svalue;
									}
								}
							}
						} else if(!isset($data[$key]) || !empty($value)){
							$data[$key] = $value;
						}
					}
				}
				$order_api = new WC_API_Orders( new WC_API_Server( '/' ) );
				if(isset($order_id)){
					$result = $order_api->update_order( [ 'order' => $data ] );
				} else {
					$order = $order_api->create_order( [ 'order' => $data ] );
					$order_id = $order['order']['id'];
				}
			}
			if(!isset($order_id)){
				$order_id = $checkout->create_order($data);
			}
			
			$order = wc_get_order($order_id);
			if(isset($data['shipping_method'])){
				if(WC()->session->get( 'chosen_shipping_methods' ) !== null){
					$ship = new WC_Order_Item_Shipping();
					$ship->set_method_id( WC()->session->get( 'chosen_shipping_methods' ) );
					$ship->set_total( WC()->session->get('cart_totals')['shipping_total'] );
					$order->add_item($ship);
					$order->save();
				} else {
					$ship = new WC_Order_Item_Shipping();
					$ship->set_method_id( $data['shipping_method'] );
					$ship->set_total( substr($data['shipping_method'], strpos($data['shipping_method'], ":") + 1) );
					$order->add_item($ship);
					$order->save();
				}
			}
			
			wp_send_json(['message' => 'Successfully created order.', 'id'=>$order_id], 200);
		} catch (\Exception $e){
			wp_send_json_error(['error' => ['Order creation failed.']], 500);
		}
	}

    public function add_terminal_reader(){
		
		$locations = WC_Stripe_Terminal_API::request(apply_filters('wc_stripe_terminal_readers', ['limit'=>1]), 'terminal/locations', $this->accessor, 'GET');
		$default_location = null;
		if(isset($locations->data) && !empty($locations->data)){
			$default_location = $locations->data[0]->id;
		} else {
			$store_full_address = [];
			$store_full_address['line1'] = (null !== WC()->countries->get_base_address() && !empty(WC()->countries->get_base_address()))? WC()->countries->get_base_address() : '123 Main St';
			//$store_full_address['line2'] = (null !== WC()->countries->get_base_address_2() && !empty(WC()->countries->get_base_address_2()))? WC()->countries->get_base_address_2() : '#1';
			$store_full_address['city'] = (null !== WC()->countries->get_base_city() && !empty(WC()->countries->get_base_city()))? WC()->countries->get_base_city() : 'New York';
			$store_full_address['state'] = (null !== WC()->countries->get_base_state() && !empty(WC()->countries->get_base_state()))? WC()->countries->get_base_state() : 'NY';
			$store_full_address['country'] = (null !== WC()->countries->get_base_country() && !empty(WC()->countries->get_base_country()))? WC()->countries->get_base_country() : 'US';
			$store_full_address['postal_code'] = (null !== WC()->countries->get_base_postcode() && !empty(WC()->countries->get_base_postcode()))? WC()->countries->get_base_postcode() : '11111';
			
			$store_name = (null !== get_the_title( get_option( 'woocommerce_shop_page_id' ) ) && !empty(get_the_title( get_option( 'woocommerce_shop_page_id' ) )))? get_the_title( get_option( 'woocommerce_shop_page_id' ) ) : 'Deault Location';
			$location = WC_Stripe_Terminal_API::request(apply_filters('wc_stripe_terminal_readers', ['address'=>$store_full_address,'display_name'=>$store_name]), 'terminal/locations', $this->accessor, 'POST');
			if(isset($location->id)){
				$default_location = $location->id;
			}
		}
        $registration_code = sanitize_text_field($_POST['registration_code']);
        $label = sanitize_text_field($_POST['label']);

        if(empty($registration_code))
            wp_send_json_error(['error' => __('Unable to communicate with the API: CODE ATL3.', 'terminal-for-stripe-and-woocommerce')], 500);
        
        $post_data = array(
            'registration_code' => $registration_code,
			'location' => $default_location
        );
        if(!empty($label))
            $post_data['label'] = $label;

        WC_Stripe_Logger::log('Info: Adding new reader to user\'s account');

        $response = WC_Stripe_Terminal_API::request(apply_filters('wc_stripe_terminal_readers', $post_data), 'terminal/readers', $this->accessor);

		if(isset($response->id) && isset($response->label)){
            $st_settings = get_option('woocommerce_stripe_terminal_settings');
            if(empty($st_settings['readers']))
                $st_settings['readers'] = array();
			
			$new_reader = array('id' => $response->id, 'label' => $response->label);
            $st_settings['readers'] = [$new_reader];
			update_option('woocommerce_stripe_terminal_settings', $st_settings);
			
			wp_send_json($new_reader);
        }else if(!empty($response->error)){
			wp_send_json_error(['error' => __('Sorry, we could not add that reader. Please try again. Remember to punch in the code on your reader and enter the string returned on the screen, into the dialogue box.', 'terminal-for-stripe-and-woocommerce')], 500);
		}
        wp_send_json_error(['error' => __('Unable to communicate with the API, please try again : CODE ATL.', 'terminal-for-stripe-and-woocommerce')], 500);
    }

    public function delete_terminal_reader(){
        $terminal_id = sanitize_text_field($_POST['id']);

        if(empty($terminal_id))
            wp_send_json_error(['error' => __('Unable to communicate with the API : CODE DTL.', 'terminal-for-stripe-and-woocommerce')], 500);

        WC_Stripe_Logger::log('Info: Deleting reader from user\'s account');

		$response = WC_Stripe_Terminal_API::request(apply_filters('wc_stripe_terminal_readers', array()), 'terminal/readers/' . $terminal_id, $this->accessor, 'DELETE');
		if(!empty($response) && !empty($response->deleted)){
            $st_settings = get_option('woocommerce_stripe_terminal_settings');
            if(empty($st_settings['readers']))
                $st_settings['readers'] = array();
            
            $readers = array();
            foreach($st_settings['readers'] as $reader){
                if($reader['id'] == $terminal_id)
                    continue;
                
                $readers[] = array('id' => $reader['id'], 'label' => $reader['label']);
            }
            $st_settings['readers'] = $readers;
			update_option('woocommerce_stripe_terminal_settings', $st_settings);
			echo $terminal_id;
			wp_die();
        }else if(!empty($response->error)){
			wp_send_json_error(['error' => $response->error], 500);
		}
        wp_send_json_error(['error' => __('Unable to communicate with the API : CODE DTL2.', 'terminal-for-stripe-and-woocommerce')], 500);
    }

    public function authenticate_terminal(){
		WC_Stripe_Logger::log('Info: Authenticating Terminal');
		$response = WC_Stripe_Terminal_API::request(apply_filters('wc_stripe_terminal_readers', array()), 'terminal/connection_tokens', $this->accessor);
		wp_send_json($response);
    }
    
    public function ajax_get_cart_details() {
		
		check_ajax_referer('_wc_stripe_terminal_nonce', 'security');

		if ( ! defined( 'TFSW_WOOCOMMERCE_CART' ) ) {
			define( 'TFSW_WOOCOMMERCE_CART', true );
		}

		if(!empty($_POST['order_pay']) && intval($_POST['order_pay']) === 1){
			$order = wc_get_order( sanitize_text_field($_POST['order_id']) );
			if($order && !is_bool($order)){
				$data = array(
					'shipping_required' => $order->needs_shipping_address(),
					'order_data'        => array(
						'currency'     => strtolower( $order->get_currency() ),
						'country_code' => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
					),
				);
				$data['order_data'] += $this->build_display_items_with_order($order);
				wp_send_json( $data );
			} else {
				wp_send_json_error();
			}
			die();
		}

		WC()->cart->calculate_totals();

		$currency = get_woocommerce_currency();

		// Set mandatory payment details.
		$data = array(
			'shipping_required' => WC()->cart->needs_shipping(),
			'order_data'        => array(
				'currency'     => strtolower( $currency ),
				'country_code' => substr( get_option( 'woocommerce_default_country' ), 0, 2 ),
			),
		);

		$data['order_data'] += $this->build_display_items();

		wp_send_json( $data );
	}
	
	/**
	 * Builds the line items to pass to Payment Request from an existing order
	 *
	 * @since 1.1.5
	 * @version 1.1.5
	 */
	protected function build_display_items_with_order($order){
		$items     = array();
		$discounts = 0;

        foreach ( $order->get_items() as $item ) {
            $amount         = $item->get_subtotal();

            $product_name = $item->get_name();

            $cart_item = array(
                'label'  => $product_name,
                'quantity' => $item->get_quantity(),
                'amount' => WC_Stripe_Helper::get_stripe_amount( $amount ),
            );

            $items[] = $cart_item;
        }

		$discounts = wc_format_decimal( $order->get_discount_total(), wc_get_price_decimals() );

		$order_total =  wc_format_decimal( $order->get_total(), wc_get_price_decimals() );

		//if ( $order->get_tax_totals() ) {
		if ( wc_tax_enabled() ) {
			$tax_total = 0;
			$tax_totals = $order->get_tax_totals();
			foreach( $tax_totals as $tax ){
				$tax_total += $tax->amount;
			}

			$items[] = array(
				'label'  => esc_html( __( 'Tax', 'terminal-for-stripe-and-woocommerce' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $tax_total ),
			);
		}

		if ( $order->needs_shipping_address() ) {
			$items[] = array(
				'label'  => esc_html( __( 'Shipping', 'terminal-for-stripe-and-woocommerce' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $order->get_shipping_total() ),
			);
		}

		if ( $order->get_discount_total() > 0 ) {
			$items[] = array(
				'label'  => esc_html( __( 'Discount', 'terminal-for-stripe-and-woocommerce' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $discounts ),
			);
		}

		$cart_fees = $order->get_fees();

		// Include fees and taxes as display items.
		foreach ( $cart_fees as $key => $fee ) {
			$items[] = array(
				'label'  => $fee->get_name(),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $fee->get_total() ),
			);
		}

		return array(
			'displayItems' => $items,
			'total'        => array(
				'label'   => $this->total_label,
				'amount'  => max( 0, apply_filters( 'woocommerce_stripe_calculated_total', WC_Stripe_Helper::get_stripe_amount( $order_total ), $order_total ) ),
				'pending' => false,
			),
		);
	}
    
    /**
	 * Builds the line items to pass to Payment Request
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	protected function build_display_items($itemized_display_items = false) {
		if ( ! defined( 'TFSW_WOOCOMMERCE_CART' ) ) {
			define( 'TFSW_WOOCOMMERCE_CART', true );
		}

		$items     = array();
		$subtotal  = 0;
		$discounts = 0;

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $amount         = $cart_item['line_subtotal'];
            $subtotal      += $cart_item['line_subtotal'];

            $product_name = WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $cart_item['data']->post->post_title : $cart_item['data']->get_name();

            $item = array(
                'label'  => $product_name,
                'quantity' => $cart_item['quantity'],
                'amount' => WC_Stripe_Helper::get_stripe_amount( $amount ),
            );

            $items[] = $item;
        }

		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$discounts = wc_format_decimal( WC()->cart->get_cart_discount_total(), WC()->cart->dp );
		} else {
			$applied_coupons = array_values( WC()->cart->get_coupon_discount_totals() );

			foreach ( $applied_coupons as $amount ) {
				$discounts += (float) $amount;
			}
		}

		$discounts   = wc_format_decimal( $discounts, WC()->cart->dp );
		$tax         = wc_format_decimal( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp );
		$shipping    = wc_format_decimal( WC()->cart->shipping_total, WC()->cart->dp );
		$items_total = wc_format_decimal( WC()->cart->cart_contents_total, WC()->cart->dp ) + $discounts;
		$order_total = version_compare( WC_VERSION, '3.2', '<' ) ? wc_format_decimal( $items_total + $tax + $shipping - $discounts, WC()->cart->dp ) : WC()->cart->get_total( false );

		if ( wc_tax_enabled() ) {
			$items[] = array(
				'label'  => esc_html( __( 'Tax', 'terminal-for-stripe-and-woocommerce' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $tax ),
			);
		}

		if ( WC()->cart->needs_shipping() ) {
			$items[] = array(
				'label'  => esc_html( __( 'Shipping', 'terminal-for-stripe-and-woocommerce' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $shipping ),
			);
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = array(
				'label'  => esc_html( __( 'Discount', 'terminal-for-stripe-and-woocommerce' ) ),
				'amount' => WC_Stripe_Helper::get_stripe_amount( $discounts ),
			);
		}

		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$cart_fees = WC()->cart->fees;
		} else {
			$cart_fees = WC()->cart->get_fees();
		}

		// Include fees and taxes as display items.
		foreach ( $cart_fees as $key => $fee ) {
			$items[] = array(
				'label'  => $fee->name,
				'amount' => WC_Stripe_Helper::get_stripe_amount( $fee->amount ),
			);
		}

		return array(
			'displayItems' => $items,
			'total'        => array(
				'label'   => $this->total_label,
				'amount'  => max( 0, apply_filters( 'woocommerce_stripe_calculated_total', WC_Stripe_Helper::get_stripe_amount( $order_total ), $order_total, WC()->cart ) ),
				'pending' => false,
			),
		);
    }
    
    public function create_payment_intent(){
        check_ajax_referer('_wc_stripe_terminal_nonce', 'security');
        
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        if(isset($gateways['stripe_terminal'])){
			$order_pay = !empty($_POST['order_pay']) && intval($_POST['order_pay']) === 1;
			$customer_id = get_current_user_id();
			$admin_id = $customer_id;
			$use_stripe_customer = false;
			if(!empty($_POST['customer_id'])){
				$customer_id = intval(sanitize_text_field($_POST['customer_id']));
				$use_stripe_customer = true;
			}
			if($order_pay){
				$order = wc_get_order(sanitize_text_field($_POST['order_id']));
				$customer_id = $order->get_customer_id();
				$admin_id = $order->get_meta('_admin_user_id');
				if($admin_id != $customer_id){
					$use_stripe_customer = true;
				}
			}

            $terminal = $gateways['stripe_terminal'];
            $order_data = array(
                'status' => apply_filters('woocommerce_default_order_status', 'pending'),
                'customer_id' => $customer_id
            );
			
			$new_order = $order_pay ? wc_get_order(sanitize_text_field($_POST['order_id'])) : wc_create_order($order_data);
			if(!$order_pay){
				foreach(WC()->cart->get_cart() as $cart_item_key => $values){
					$new_order->add_product(
						$values['data'], $values['quantity'], array(
							'variation' => $values['variation'],
							'totals' => array(
								'subtotal' => $values['line_subtotal'],
								'subtotal_tax' => $values['line_subtotal_tax'],
								'total' => $values['line_total'],
								'tax' => $values['line_tax'],
								'tax_data' => $values['line_tax_data'] // Since 2.2
							)
						)
					);
				}

				if ( WC()->cart->needs_shipping() ) {
					$shipping_methods = WC()->cart->calculate_shipping();
					foreach($shipping_methods as $shipping_method){
						$shipping_item = new WC_Order_Item_Shipping();
						$shipping_item->set_props(
							array(
								'method_title' => $shipping_method->label,
								'method_id' => $shipping_method->method_id,
								'instance_id' => $shipping_method->instance_id,
								'total' => wc_format_decimal( $shipping_method->cost ),
								'taxes' => array(
									'total' => $shipping_method->taxes
								)
							)
						);
						foreach($shipping_method->get_meta_data() as $key => $value){
							$shipping_item->add_meta_data( $key, $value, true );
						}
						$new_order->add_item( $shipping_item );
					}
				}

				$new_order->update_meta_data( '_admin_user_id', $order_data['customer_id'] );
				
				$new_order->calculate_totals();
			}

            $intent = $terminal->create_intent($new_order, null, $this->accessor, $order_pay);
            if(empty($intent->error)){
                wp_send_json(array('order_id' => WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $new_order->id : $new_order->get_id(), 'intent' => $intent->id, 'client_secret' => $intent->client_secret));
            }else
				wp_send_json_error(['error' => $intent->error], 500);
		}
        wp_send_json_error(['error' => __('The stripe terminal gateway is not enabled.', 'terminal-for-stripe-and-woocommerce')], 500);
    }

    public function recreate_payment_intent(){
	    $order_id = sanitize_text_field($_POST['order_id']);
        check_ajax_referer('_wc_stripe_terminal_nonce', 'security');

        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        if(isset($gateways['stripe_terminal'])){
            $terminal = $gateways['stripe_terminal'];
            $order = !empty($order_id) ? wc_get_order($order_id) : null;
            if(!empty($order)){
                $intent = $terminal->create_intent($order, null, $this->accessor, true);
                if(empty($intent->error)){
                    wp_send_json(array('order_id' => WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id(), 'intent' => $intent->id, 'client_secret' => $intent->client_secret));
                }else
					wp_send_json_error(['error' => $intent->error], 500);
            }
		}
        wp_send_json_error(['error' => __('The stripe terminal gateway is not enabled.', 'terminal-for-stripe-and-woocommerce')], 500);
    }

    public function capture_payment_intent(){
        $order_id = sanitize_text_field($_POST['order_id']);
        check_ajax_referer('_wc_stripe_terminal_nonce', 'security');

        $order = !empty($order_id) ? wc_get_order($order_id) : null;
        if(!empty($order)){
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            if(isset($gateways['stripe_terminal'])){
                $terminal = $gateways['stripe_terminal'];
                wp_send_json($terminal->process_payment(WC_Stripe_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id()));
            }
        }
        wp_send_json_error(array('error' => __('Invalid order', 'terminal-for-stripe-and-woocommerce')), 500);
    }

    public function cancel_payment_intent(){
        $order_id = sanitize_text_field($_POST['order_id']);
        check_ajax_referer('_wc_stripe_terminal_nonce', 'security');

        $order = !empty($order_id) ? wc_get_order($order_id) : null;
        if(!empty($order)){
            $intent_id = get_post_meta($order_id, '_stripe_intent_id', true);
            delete_post_meta($order_id, '_stripe_intent_id');
            WC_Stripe_Logger::log('Info: Cancelling Payment Intent');
		    WC_Stripe_Terminal_API::request(array(), 'payment_intents/' . $intent_id . '/cancel', $this->accessor);
        }

        wp_die();
    }

    public function cancel_order(){
        $order_id = sanitize_text_field($_POST['order_id']);
        check_ajax_referer('_wc_stripe_terminal_nonce', 'security');

        $order = !empty($order_id) ? wc_get_order($order_id) : null;
        if(!empty($order)){
            $intent_id = get_post_meta($order_id, '_stripe_intent_id', true);
            if(!empty($intent_id)){
                delete_post_meta($order_id, '_stripe_intent_id');
                WC_Stripe_Logger::log( 'Info: Cancelling Payment Intent' );
                WC_Stripe_Terminal_API::request(array(), 'payment_intents/' . $intent_id . '/cancel', $this->accessor);
            }
            WC_Stripe_Logger::log( 'Info: Cancelling Order' );
            $order->update_status( 'cancelled' );
        }

        wp_die();
    }

    /**
	 * Adds or removes a newsletter subscription if the user has opt-in.
	 * @throws WC_Stripe_Exception
	 */
	public function add_or_remove_subscription(){
		$user = wp_get_current_user();

		if (($user instanceof WP_User) && $user->exists() && !empty($user->user_email)) {
			$has_subscribed = (isset($_POST['has_subscribed']) && !empty($_POST['has_subscribed']) );
			$st_settings = get_option('woocommerce_stripe_terminal_settings');
			$st_settings['subscribe'] = $has_subscribed ? 'yes' : 'no';
			$api_url = 'https://store.arcanestrategies.com/api/newsletter';
			$domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
			$body = array('email' => $user->user_email, 'has_subscribed' => $has_subscribed, 'website' => $domain);

			// If the user has opt-in, add their email to the newsletter, otherwise unsubscribe them.
			$response = wp_remote_post($api_url , array(
				'body' => json_encode($body),
				'headers' => array('Content-Type' => 'application/json'),
			));
			
			if (!($response instanceof WP_Error) && $response['response']['code'] == 200) {
				update_option('woocommerce_stripe_terminal_settings', $st_settings);
				wp_send_json(['message' => $has_subscribed ? 'Subscribed' : 'Unsubscribed'], 200);
			}

			try {
				$response_data = json_decode($response['body'], true);
				$error_response_message = isset($response_data['error']) ? $response_data['error'] : 'Could not connect to API';
				wp_send_json_error(['error' => ['message' => $error_response_message]], $response['response']['code']);
			}
			catch (\Exception $e) {
			}
		}

		wp_send_json_error(['error' => ['message' => 'Invalid email']], 400);
	}
}

new Tfsw_Stripe_Terminal_Ajax();