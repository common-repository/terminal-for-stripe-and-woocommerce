<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_TFSW_API{
	private static $APP_VERSION = '2.2.0';
	private static $DEFAULT_TIMEZONE = 'UTC';
	private static $HASH_ALGO = 'sha512';
	private static $KEY_SIZE = 64;
	private static $API_KEY_TTL = 86400; // 24 hours
	private static $instance;

	private $api_namespace = 'tfsw/v1';
	private $api_routes;

	private $gateway_instance;

	/**
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Private unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	private function __wakeup() {}

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * *Singleton* via the `new` operator from outside of this class.
	 */
	private function __construct() {
		$this->gateway_instance = new Tfsw_Gateway_Stripe_Terminal(false);
		$this->api_routes = array(
			array(
				'route' => 'auth',
				'endpoints' => array(
					'methods' => 'POST',
					'callback' => array( $this, 'auth' ),
					'permission_callback' => '__return_true'
				)
			),
			array(
				'route' => 'account',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'account' ),
					'permission_callback' => '__return_true'
				)
			),
			array(
				'route' => 'version',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'version' ),
					'permission_callback' => '__return_true'
				)
			),
			array(
				'route' => 'products',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'products' ),
					'permission_callback' => '__return_true'
				)
			),
 			array(
				'route' => 'categories',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'categories' ),
					'permission_callback' => '__return_true'
				)
			),
 			array(
				'route' => 'locations',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'locations' ),
					'permission_callback' => '__return_true'
				)
			),
 			array(
				'route' => 'country',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'country' ),
					'permission_callback' => '__return_true'
				)
			),
			array(
				'route' => 'currency',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'currency' ),
					'permission_callback' => '__return_true'
				)
			),
			array(
				'route' => 'tips',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'tips' ),
					'permission_callback' => '__return_true'
				)
			),
			array(
				'route' => 'products/(?P<id>[\d]+)',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'product' ),
					'permission_callback' => '__return_true'
				)
			),
			array(
				'route' => 'products/(?P<id>[\d]+)/variants',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'product_variants' ),
					'permission_callback' => '__return_true'
				)
			),
			array(
				'route' => 'customers',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'getUserByEmail' ),
					'permission_callback' => '__return_true'
				)
			),
			array(
				'route' => 'order',
				'endpoints' => array(
					array(
						'methods' => 'GET',
						'callback' => array( $this, 'get_orders' ),
						'permission_callback' => '__return_true'
					),
					array(
						'methods' => 'POST',
						'callback' => array( $this, 'create_order' ),
						'permission_callback' => '__return_true'
					)
				)
			),
			array(
				'route' => 'order/(?P<id>[\d]+)',
				'endpoints' => array(
					array(
						'methods' => 'GET',
						'callback' => array( $this, 'get_order' ),
						'permission_callback' => '__return_true'
					),
					array(
						'methods' => 'PUT',
						'callback' => array( $this, 'update_order' ),
						'permission_callback' => '__return_true'
					),
					array(
						'methods' => 'DELETE',
						'callback' => array( $this, 'delete_order' ),
						'permission_callback' => '__return_true'
					)
				)
			),
			array(
				'route' => 'order/(?P<id>[\d]+)/shipping/methods',
				'endpoints' => array(
					'methods' => 'GET',
					'callback' => array( $this, 'shipping_methods' ),
					'permission_callback' => '__return_true'
				)
			)
		);

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		$this->add_requirements();
	}

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Singleton The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers the api endpoints to wp-json.
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	public function register_routes() {
		foreach($this->api_routes as $route){
			register_rest_route( $this->api_namespace, $route['route'], $route['endpoints'] );
		}
	}

	/**
	 * Require necessary classes.
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	private function add_requirements(){
		require_once dirname(dirname(dirname( __FILE__ ))) . '/woocommerce/includes/legacy/api/v3/class-wc-api-exception.php';
		require_once dirname(dirname(dirname( __FILE__ ))) . '/woocommerce/includes/legacy/api/v3/interface-wc-api-handler.php';
		require_once dirname(dirname(dirname( __FILE__ ))) . '/woocommerce/includes/legacy/api/v3/class-wc-api-json-handler.php';
		require_once dirname(dirname(dirname( __FILE__ ))) . '/woocommerce/includes/legacy/api/v3/class-wc-api-server.php';
		require_once dirname(dirname(dirname( __FILE__ ))) . '/woocommerce/includes/legacy/api/v3/class-wc-api-resource.php';
		require_once dirname(dirname(dirname( __FILE__ ))) . '/woocommerce/includes/legacy/api/v3/class-wc-api-orders.php';
	}

	/**
	 * Generates a new API Key and returns it along with the hash for storage.
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	private function generate_api_key(){
		$key = '';

        while(($len = strlen($key)) < self::$KEY_SIZE){
            $size = self::$KEY_SIZE - $len;

            $bytes = random_bytes($size);

            $key .= substr( str_replace( ['/', '+', '='], '', base64_encode( $bytes ) ), 0, $size );
		}

		$hash = hash( self::$HASH_ALGO, $key );
		return array( 'key' => $key, 'hash' => $hash );
	}

	/**
	 * Validates that the provided user id and stfw-token pair are valid.
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	private function validate_token(WP_REST_Request $request){
		$invalid = new WP_Error( 'invalid_token', __( 'Your API token was invalid.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 401 ) );

		$user_id = $request->get_header('User');
		$key = $request->get_header('Stfw-Token');
		if(empty( $user_id ) || empty( $key )){
			return new WP_Error( 'missing_token', __( 'You must provide a valid API token and User ID to access this endpoint.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 401 ) );
		}

		$user_id = intval( $user_id );
		$key = hash( self::$HASH_ALGO, $key );
		$st_settings = get_option( 'woocommerce_stripe_terminal_settings' );
		if(!isset( $st_settings['api_keys'] ) || !is_array( $st_settings['api_keys'] )){
			$st_settings['api_keys'] = array();
			update_option( 'woocommerce_stripe_terminal_settings', $st_settings );
			return $invalid;
		}

		if(empty( $st_settings['api_keys'][$user_id] )){
			return $invalid;
		}

		if(( new DateTime( $st_settings['api_keys'][$user_id]['expires'] ) ) <= ( new DateTime( 'now', new DateTimeZone( self::$DEFAULT_TIMEZONE ) ) )){
			unset( $st_settings['api_keys'][$user_id] );
			update_option( 'woocommerce_stripe_terminal_settings', $st_settings );
			return new WP_Error( 'expired_token', __( 'Your API token has expired.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 401 ) );
		}

		if(!hash_equals( $st_settings['api_keys'][$user_id]['key'], $key )){
			return $invalid;
		}

		wp_set_current_user( $user_id );
		return true;
	}

	/**
	 * Authenticates a user with the provided email/password combination and generates an API token if successful.
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	public function auth(WP_REST_Request $request){
		if(!$request->has_param( 'email' ) || !$request->has_param( 'password' )){
			return new WP_Error( 'missing_params', __( 'Missing required parameters.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 400 ) );
		}
		$user = wp_authenticate( sanitize_text_field( $request->get_param( 'email' ) ), sanitize_text_field( $request->get_param( 'password' ) ) );
		if(is_wp_error( $user )){
			return new WP_Error( $user->get_error_code(), strip_tags( $user->get_error_message() ), array( 'status' => 401 ) );
		}

		$flag = false;
		foreach(['administrator','shop_manager','kiosk','cashier'] as $role){
			if(in_array( $role , (array) $user->roles )){
				$flag = true;
				break;
			}	
		}
		if($flag==false){
			return new WP_Error( 'forbidden', 'You are not authorized to use this API.', array( 'status' => 403 ) );
		}

		$api_key = $this->generate_api_key();
		$st_settings = get_option( 'woocommerce_stripe_terminal_settings' );
		if(!isset( $st_settings['api_keys'] ) || !is_array( $st_settings['api_keys'] )){
			$st_settings['api_keys'] = array();
		}

		$expires = (new DateTime( 'now', new DateTimeZone( self::$DEFAULT_TIMEZONE ) ))->add( new DateInterval( 'PT' . self::$API_KEY_TTL . 'S' ) )->format( 'c' );
		$st_settings['api_keys'][$user->ID] = array( 'key' => $api_key['hash'], 'expires' => $expires );
		update_option( 'woocommerce_stripe_terminal_settings', $st_settings );
		
		return new WP_REST_Response( array(
			'id' => $user->ID,
			'stfw_token' => $api_key['key'],
			'expires' => $expires
		) );
	}
	
	/**
	 * Returns the stored locations
	 *
	 * @since 2.2
	 * @version 2.2
	 */
	public function locations(WP_REST_Request $request){
		$st_settings = get_option('woocommerce_stripe_terminal_settings');
		if(isset($st_settings['locations'])){
			return $st_settings['locations'];
		}
		return new WP_Error( 'location_not_found', 'Location management is unavailable on this version.', array( 'status' => 404 ) );
	}
	

	/**
	 * Returns the default country
	 *
	 * @since 2.2
	 * @version 2.2
	 */
	public function country(WP_REST_Request $request){
		if(function_exists('wc_get_base_location')){
			if(null !== wc_get_base_location()['country']){
				return strtolower(wc_get_base_location()['country']);
			}
		}
		return 'usa';
	}

	/**
	 * Returns the default currency
	 *
	 * @since 2.2
	 * @version 2.2
	 */
	public function currency(WP_REST_Request $request){
		if(function_exists('get_woocommerce_currency')){
			return strtolower(get_woocommerce_currency());
		}
		return 'usd';
	}
	
	/**
	 * Returns the stored tips
	 *
	 * @since 2.2
	 * @version 2.2
	 */
	public function tips(WP_REST_Request $request){
		$st_settings = get_option('woocommerce_stripe_terminal_settings');
		// REFACTOR: 2.2 uses hard-coded values
		$tips = [0,15,20,25];
		$default_tip = 2;	// 2nd key = 20
		$post_tax = false;
		if(isset($st_settings['tips']) && is_string($st_settings['tips'])){
			$tips = explode(',',$st_settings['tips']);
		}
		if(isset($st_settings['tip_default'])){
			$default_tip = $st_settings['tip_default'];
		}
		return new WP_REST_Response( ['tips'=>$tips, 'default_tip'=>$default_tip, 'post_tax'=>false] );
	}

	/**
	 * Returns the stripe connect account id.
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	public function account(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}

		$account_id = $this->gateway_instance->get_option( 'stripe_connect_id' );
		if(empty( $account_id )){
			return new WP_Error( 'empty_id', __( 'The WP Admin has not provided a Stripe Connect account id.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $account_id );
	}

	/**
	 * Returns the api app version.
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	public function version(){
		return rest_ensure_response( self::$APP_VERSION );
	}
	
	/**
	 * Returns a resultset of all categories
	 *
	 * @since 2.1.0
	 * @version 2.1.0
	 */
	public function categories(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}
		
		$orderby = 'name';
		$order = 'asc';
		$hide_empty = true;
		$cat_args = array(
			'orderby'    => $orderby,
			'order'      => $order,
			'hide_empty' => $hide_empty,
		);
		 
		$product_categories = apply_filters( 'wc_products_apply_get_data', get_terms( 'product_cat', $cat_args ) );

		return new WP_REST_Response( $product_categories );
	}

	/**
	 * Returns a paginated resultset of all published products.
	 *
	 * @since 1.1.4
	 * @version 2.1.0
	 */
	public function products(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}

		$args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => -1
		);
		
		$tax_args = $meta_query = $id_query = [];
		
		if($request->has_param( 'category_ids' )){
			$cats = $request->get_param( 'category_ids' );
			$tax_args = [
									[
										'taxonomy'  => 'product_cat',
										'field'     => 'term_id', 
										'terms'     => explode(',',$cats)
									]
								];
			$args['tax_query'] = $tax_args;
		}
		if($request->has_param('search')){
			$search = $request->get_param( 'search' );
			$args['s'] = $search;
		}
		if($request->has_param('sku')){
			$sku = $request->get_param( 'sku' );
			$args['p'] = $sku;
			if(null!== ($temp_count = new WP_Query( $args )) && $temp_count->found_posts > 0){
				$id_query[] = $sku;
			} else {
				$args['p'] = wc_get_product_id_by_sku($sku);
				if(!empty($args['p']) && null!== ($temp_count = new WP_Query( $args )) && $temp_count->found_posts > 0){
					$id_query[] = $args['p'];
				} else {
					return new WP_Error( 'no_published_products', __( 'The WP Admin has not published any products.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 500 ) );
				}
			}
		}
		
		$product_counts = new WP_Query( $args );	
		
		if($product_counts->found_posts <= 0){
			return new WP_Error( 'no_published_products', __( 'The WP Admin has not published any products.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 500 ) );
		}

		$page = $request->has_param( 'page' ) ? intval( $request->get_param( 'page' ) ) : 1;
		$limit = $request->has_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 20;
		
		$types = wc_get_product_types();
		unset($types['external']);
		$args = array(
					'limit' => $limit,
					'page' => $page,
					'orderby' => 'name',
					'order' => 'ASC',
					'paginate' => true,
					'type' => array_merge( array_keys( $types ) )
				);

		// For improved UI, we filter out draft and hidden/pending products.  The 'visibility' param is handled on the app itself as it is not a WC API param
		$args['status'] = 'private,publish';

		if(!empty($id_query)){
			$args['p'] = $id_query[0];
		} else {
			if(!empty($tax_args)){
				$args['tax_query'] = $tax_args;
			}
			if($request->has_param('search')){
				$search = $request->get_param( 'search' );
				$args['s'] = $search;
			}			
 		}
		
		$products = apply_filters( 'wc_products_apply_get_data', wc_get_products( $args ));

		return new WP_REST_Response( $products );
	}

	/**
	 * Returns the product associated with the provided id.
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	public function product(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}

		$product = wc_get_product( intval( $request->get_param( 'id' ) ) );
		if(empty( $product )){
			return new WP_Error( 'product_not_found', __( 'We could not find a product with the given product id.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 404 ) );
		}

		$data = $product->get_data();
		$data['has_variants'] = method_exists( $product, 'get_available_variations' );
		return new WP_REST_Response( $data );
	}

	/**
	 * Returns the product variants associated with the provided product id.
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	public function product_variants(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}

		$product = wc_get_product( intval( $request->get_param( 'id' ) ) );
		if(empty( $product )){
			return new WP_Error( 'product_not_found', __( 'We could not find a product with the given product id.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 404 ) );
		}

		if(!method_exists( $product, 'get_available_variations' )){
			return new WP_Error( 'invalid_product', __( 'The provided product is not a variable product.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 400 ) );
		}
		
		return new WP_REST_Response( $product->get_available_variations() );
	}
	
	/**
	 * Returns a user id belonging to the email address sent, or a 404 error if none exists.
	 * 
	 * @since 2.1.1
	 * @version 2.1.1
	 */
	public function getUserByEmail(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}

		if(!$request->has_param( 'email' )){
			return new WP_Error( 'missing_email', __( 'You must provide a valid user email to search for.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 400 ) );
		}

		$user_email = sanitize_text_field($request->get_param( 'email' ));
		$user = get_user_by( 'email',  $user_email );
		if(empty($user)){
			return new WP_Error( 'user_not_found', __( 'There was no user associated with the email address provided.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 404 ) );
		}
		
		return new WP_REST_Response( $user->ID );
	}

	/**
	 * Creates a new order.
	 *
	 * @since 1.1.4
	 * @version 2.2.0
	 */
	public function create_order(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}

		$data = $request->get_json_params();
		if(!empty( $data )){
			$data['payment_method'] = $this->gateway_instance->id;
			$data['payment_method_title'] = $this->gateway_instance->get_title();
			$data['set_paid'] = false;
			if(!empty( $data['line_items'] )){
				foreach($data['line_items'] as $key => $line_item){
					if(empty($line_item['product_id']) || false !== strpos($line_item['product_id'],'custom') || ($line_item['product_id']=='tip' || (isset($line_item['product_name']) && ($line_item['product_name']=="Tips/Fees" || $line_item['product_name']=="Tips\/Fees")))){
						if(!isset($data['fee_lines'])){
								$data['fee_lines'] = [];
						}
						for($i = 0; $i<$line_item['quantity']; $i++){
								$data['fee_lines'][] = [
										'name' => isset($line_item['product_name'])? $line_item['product_name'] : 'Miscellaneous Item',
										'title' => isset($line_item['product_name'])? $line_item['product_name'] : 'Miscellaneous Item',
										'total_tax' => ($line_item['product_id']=='tip' || $line_item['product_name']=="Tips/Fees" || $line_item['product_name']=="Tips\/Fees")? '0' : (!empty($line_item['subtotal_tax'])? '' : '0'),
										'tax_class' => ($line_item['product_id']=='tip' || $line_item['product_name']=="Tips/Fees" || $line_item['product_name']=="Tips\/Fees")? null : (!empty($line_item['subtotal_tax'])? 'standard' : null),
										'tax_status' => ($line_item['product_id']=='tip' || $line_item['product_name']=="Tips/Fees" || $line_item['product_name']=="Tips\/Fees")? 'none' : (!empty($line_item['subtotal_tax'])? 'taxable' : 'none'),
										'taxable' => ($line_item['product_id']=='tip' || $line_item['product_name']=="Tips/Fees" || $line_item['product_name']=="Tips\/Fees")? false : (!empty($line_item['subtotal_tax'])? true : false),
										'total' => $line_item['price']
								];
								// REFACTOR: Setting 0 tax rates on fee_lines like this does not seem to work
						}
						unset( $data['line_items'][$key] );
					}
					if(!empty( $line_item['variation_id'] )){
						$product = wc_get_product( intval( $line_item['product_id'] ) );
						if(method_exists( $product, 'get_available_variations' )){
							$found = false;
							$variation_id = intval( $line_item['variation_id'] );
							$variations = $product->get_available_variations();
							foreach($variations as $variation){
								if($variation_id === $variation['variation_id']){
									$found = true;
									break;
								}
							}
							
							if($found === false){
								$message = sprintf( __( 'The provided variation "%d" is not a valid variation of the product "%d"', 'terminal-for-stripe-and-woocommerce' ), $variation_id, $line_item['product_id'] );
								return new WP_Error( 'invalid_variation', $message, array( 'status' => 400 ) );
							}

							$data['line_items'][$key]['product_id'] = $variation_id;
						}

						unset( $data['line_items'][$key]['variation_id'] );
					}
				}
			}
			$data = array( 'order' => $data );
		}

		$order_api = new WC_API_Orders( new WC_API_Server( '/' ) );
		$response = $order_api->create_order( $data );
		if($response instanceof WP_Error || empty($response) || empty($response['order'])){
			return $response;
		}

		$order = wc_get_order( $response['order']['id'] );
		$order->update_meta_data( '_admin_user_id', wp_get_current_user()->ID );
		
		// When location ID is present, get the zip code.  No billing information is presented when an order is created, so we will assign the zip code from the location
		if(isset($data['order']['location_id'])){
			$location_id = $data['order']['location_id'];
		} else {
			$cashier = get_user_meta(wp_get_current_user()->ID);
			if(isset($cashier) && isset($cashier['woocommerce_stripe_terminal_user_location'])){
				$location_id = $cashier['woocommerce_stripe_terminal_user_location'][0];
			}
		}
		if($location_id){
			$st_settings = get_option('woocommerce_stripe_terminal_settings');
			if(isset($st_settings['locations']) && null!== ($lox = $st_settings['locations'][$location_id])){
				$data['billing']['address_1'] = isset($lox['line1'])? $lox['line1'] : null;
				$data['billing']['city'] = isset($lox['city'])? $lox['city'] : null;
				$data['billing']['state'] = isset($lox['state'])? $lox['state'] : null;
				$data['billing']['postcode'] = isset($lox['postal_code'])? $lox['postal_code'] : null;
				$data['billing']['country'] = (isset($lox['country']) && $lox['country']!=='c')? $lox['country'] : 'US';
				foreach($data['billing'] as $bill_key => $bill_prop){
					$fxn = 'set_billing_'.$bill_key;
					$order->$fxn($bill_prop);
					$fxn = 'set_shipping_'.$bill_key;
					$order->$fxn($bill_prop);
				}
			}
		}
		
		$order->save();
		
		self::order_calculate($order->get_id());
		return self::get_order_direct( $order->get_id() );
	}

	/**
	 * Get an existing order
	 *
	 * @since 1.1.4
	 * @version 2.2.0
	 */
	public function get_order(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}
		
		$id = intval( $request->get_param( 'id' ) );
		return self::get_order_direct($id);
	}
	
	/**
	 * Get an existing order
	 * @since 2.1.5
	 * @version 2.2.0
	 */	
	public function get_order_direct($id){
		
		$order = wc_get_order( $id );

		if(empty( $order )){
			return new WP_Error( 'order_not_found', __( 'We could not find an order with the given order id.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 404 ) );
		}

		$order_api = new WC_API_Orders( new WC_API_Server( '/' ) );
		$order_data = $order_api->get_order( $order->get_id() );
		$fee_subtotal = 0.0;
		for($i=0; $i < count($order_data['order']['fee_lines']); $i++){
			$order_data['order']['fee_lines'][$i]['quantity'] = (isset($order_data['order']['fee_lines'][$i]['quantity']))? $order_data['order']['fee_lines'][$i]['quantity'] : 1;
			$order_data['order']['fee_lines'][$i]['name'] = (!isset($order_data['order']['fee_lines'][$i]['name']))? $order_data['order']['fee_lines'][$i]['title'] : $order_data['order']['fee_lines'][$i]['name'];
			$order_data['order']['fee_lines'][$i]['subtotal'] = $order_data['order']['fee_lines'][$i]['price'] = (!isset($order_data['order']['fee_lines'][$i]['price']))? $order_data['order']['fee_lines'][$i]['total'] : $order_data['order']['fee_lines'][$i]['price'];
			$order_data['order']['fee_lines'][$i]['subtotal_tax'] = $order_data['order']['fee_lines'][$i]['total_tax'] = isset($order_data['order']['fee_lines'][$i]['total_tax'])? $order_data['order']['fee_lines'][$i]['total_tax'] : 0;
			$order_data['order']['fee_lines'][$i]['product_id'] = ($order_data['order']['fee_lines'][$i]['name'] != "Tips/Fees" && $order_data['order']['fee_lines'][$i]['name'] != "Tips\/Fees")? 'custom_'.$i : 'tip';
			$fee_subtotal += $order_data['order']['fee_lines'][$i]['subtotal'];
		}
		$order_data['order']['line_items'] = array_merge($order_data['order']['line_items'],$order_data['order']['fee_lines']);
		unset($order_data['order']['fee_lines']);
		$subtotal = $order_data['order']['subtotal'] + $fee_subtotal;
		$order_data['order']['subtotal'] = number_format($subtotal,2);

		return $order_data;
	}

	/**
	 * Get existing orders
	 *
	 * @since 2.1.1
	 * @version 2.2.0
	 */
	public function get_orders(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}
		
		$page = $request->has_param( 'page' ) ? intval( $request->get_param( 'page' ) ) : 1;
		$limit = $request->has_param( 'limit' ) ? intval( $request->get_param( 'limit' ) ) : 20;
		$args = array(
					'limit' => $limit,
					'page' => $page,
					'orderby' => 'date',
					'order' => 'DESC',
					'paginate' => true
				);
		// v2.1.x app requires statuses in order to filter down to those required for processing
		if($request->has_param( 'status' ) && !empty($request->get_param( 'status' )) && $request->get_param( 'status' ) !== 'false'){
			$args['status'] = ['wc-pending', 'wc-failed', 'wc-on-hold'];
			if($request->get_param( 'status' ) == 2){
				$args['status'] = ['wc-processing', 'wc-completed', 'wc-cancelled', 'wc-refunded'];
			}
		}
		if($request->has_param( 'customer' ) && !empty($request->get_param( 'customer' )) && $request->get_param( 'customer' ) !== 'false'){
			$user = get_user_by( 'email',  $request->get_param( 'customer' ) );
			if(!empty($user)){
				$args['customer'] = $user->ID;
				$args['customer_id'] = $user->ID;
			} else {
				return new WP_Error( 'order_not_found', __( 'Customer not found.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 404 ) );
			}
 		}
		$orders = wc_get_orders( $args );
		if(empty( $orders )){
			return new WP_Error( 'order_not_found', __( 'We could not find orders matching those parameters.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 404 ) );
		}
		
		if(isset($args['status']) && is_array($args['status']) && count($args['status']) > 0){
			for($i=0; $i < count($args['status']); $i++){
				$args['status'][$i] = str_replace('wc-','',$args['status'][$i]);
			}
			$args['status']	= implode(",",$args['status']);
		}
		
		$order_api = new WC_API_Orders( new WC_API_Server( '/' ) );
		
		$order_data = $order_api->get_orders( null, $args );

		for($z=0; $z < count($order_data['orders']); $z++){
			$fee_subtotal = 0.0;
			for($i=0; $i < count($order_data['orders'][$z]['fee_lines']); $i++){
				$order_data['orders'][$z]['fee_lines'][$i]['quantity'] = (isset($order_data['orders'][$z]['fee_lines'][$i]['quantity']))? $order_data['orders'][$z]['fee_lines'][$i]['quantity'] : 1;
				$order_data['orders'][$z]['fee_lines'][$i]['name'] = (!isset($order_data['orders'][$z]['fee_lines'][$i]['name']))? $order_data['orders'][$z]['fee_lines'][$i]['title'] : $order_data['orders'][$z]['fee_lines'][$i]['name'];
				$order_data['orders'][$z]['fee_lines'][$i]['subtotal'] = $order_data['orders'][$z]['fee_lines'][$i]['price'] = (!isset($order_data['orders'][$z]['fee_lines'][$i]['price']))? $order_data['orders'][$z]['fee_lines'][$i]['total'] : $order_data['orders'][$z]['fee_lines'][$i]['price'];
				$order_data['orders'][$z]['fee_lines'][$i]['subtotal_tax'] = $order_data['orders'][$z]['fee_lines'][$i]['total_tax'] = isset($order_data['orders'][$z]['fee_lines'][$i]['total_tax'])? $order_data['orders'][$z]['fee_lines'][$i]['total_tax'] : 0;
				$order_data['orders'][$z]['fee_lines'][$i]['product_id'] = ($order_data['orders'][$z]['fee_lines'][$i]['name'] != "Tips/Fees" && $order_data['orders'][$z]['fee_lines'][$i]['name'] != "Tips\/Fees")? 'custom_'.$i : 'tip';
				$fee_subtotal += $order_data['orders'][$z]['fee_lines'][$i]['subtotal'];
			}
			$order_data['orders'][$z]['line_items'] = array_merge($order_data['orders'][$z]['line_items'],$order_data['orders'][$z]['fee_lines']);
			unset($order_data['orders'][$z]['fee_lines']);
			
			$subtotal = $order_data['orders'][$z]['subtotal'] + $fee_subtotal;
			$order_data['orders'][$z]['subtotal'] = number_format($subtotal,2);
		}
		
		return $order_data;
	}
	
	/**
	 * Update customer information with order billing and shipping data
	 * @since 2.1.5
	 * @version 2.1.5
	 */	
	public function setCustomerDetails($user_id,$order){
			// Update Customer billing information if provided
			$customer_properties = [
										'shipping_first_name',
										'shipping_last_name',
										'shipping_country',
										'shipping_address_1',
										'shipping_address_2',
										'shipping_city',
										'shipping_state',
										'shipping_postcode',
										'billing_first_name',
										'billing_last_name',
										'billing_country',
										'billing_address_1',
										'billing_address_2',
										'billing_city',
										'billing_state',
										'billing_postcode',
										'billing_phone'
									];
			
			$customer = new WC_Customer($user_id);
			if($customer){
				foreach($customer_properties as $key){
					$fnx = 'get_'.$key;
					$fnx_2 = 'set_'.$key;
					if(method_exists($order, $fnx) && method_exists($customer, $fnx_2)){
						try {
							if(!empty($order->$fnx())){
								$val = $order->$fnx();
								$customer->$fnx_2($val);
								//update_user_meta( $user_id, $key, $val );
							}
						} catch (\Exception $e){
							// Do nothing
						}
					}
				}
				$customer->save();
			}
	}
	
	/**
	 * Recalculates the price of an existing order
	 * 
	 * @since 2.2.0
	 * @version 2.2.0
	 */
	public function order_calculate($order_id,$discount_amount=0,$taxes_only=false){
		$order = wc_get_order( $order_id );		
		if($taxes_only){
			if('yes' !== get_option( 'woocommerce_prices_include_tax' )){
				$address_properties = [
											'country',
											'city',
											'state',
											'postcode',
										];
				
				$calculate_taxes_for = [];
				
				$typer = get_option( 'woocommerce_tax_based_on' );
			
				foreach($address_properties as $prop){
					$fxn = 'get_'.$typer.'_'.$prop;
					if(method_exists($order, $fxn)){
						$calculate_taxes_for[$prop] = $order->$fxn();
						if($prop == 'country' && (empty($calculate_taxes_for[$prop]) || $calculate_taxes_for[$prop] == 'c')){
								$calculate_taxes_for[$prop] = 'US';
						}
					}
				}
			}
			$order->calculate_taxes($calculate_taxes_for);
		}
		
		$order->calculate_totals();
		$order->save();
		if($discount_amount > 0){
			$new_total = $order->get_total() - number_format($discount_amount,2);
			$order->set_total($new_total);
			$order->save();
		}
		return $order;
	}

	/**
	 * Update an existing order
	 *
	 * @since 1.1.1
	 * @version 2.2.0
	 */
	public function update_order(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		
		if($token_valid !== true){
			return $token_valid;
		}

		$order = wc_get_order( intval( $request->get_param( 'id' ) ) );
		
		if(empty( $order )){
			return new WP_Error( 'order_not_found', __( 'We could not find an order with the given order id.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 404 ) );
		}
		
		$discount_code = false;
		$discount_codes = $discount_amounts = [];
		
		$flag = false;
		$status = $order->get_status();
		$order_items = $order->get_items();
		$data = $request->get_json_params();
		$discount_amount = 0;
		
		if(!empty( $data )){
			if(!empty($data['coupon_lines'])){
				for($i = 0; $i < count($data['coupon_lines']); $i++){
					if(!isset($data['coupon_lines'][$i])){
						continue;
					}
					$coupon = new WC_Coupon($data['coupon_lines'][$i]['code']);
					if($coupon && $coupon->get_amount() > 0){
						if('percent' === $coupon->get_discount_type()){
							$data['coupon_lines'][$i]['amount'] = ($order->get_total())*(($coupon->get_amount())/100);
							$data['coupon_lines'][$i]['discount'] = ($order->get_total())*(($coupon->get_amount())/100);				
						} else {
							$data['coupon_lines'][$i]['amount'] = $coupon->get_amount();
							$data['coupon_lines'][$i]['discount'] = $coupon->get_amount();
						}
						$discount_code = true;
						$discount_codes[] = $data['coupon_lines'][$i]['code'];
						$discount_amounts[$i] = $data['coupon_lines'][$i]['amount'];
					}
				}
				if(!$discount_code){
					return new WP_Error( 'invalid_product', __( 'Invalid discount code.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 403 ) );
				}
			}
			if(!empty($order->get_coupon_codes())) {
					foreach( $order->get_coupon_codes() as $existing_order_coupon ) {
							if(empty($data['coupon_lines']) || empty($discount_codes) || (!in_array($existing_order_coupon,$discount_codes))){
									$order->remove_coupon($existing_order_coupon);
							}
							// If this coupon already exists, no need to add it again
							if(!empty($data['coupon_lines'])){
								for($i = 0; $i < count($data['coupon_lines']); $i++){
									if($existing_order_coupon == $data['coupon_lines'][$i]['code']){
										unset($data['coupon_lines'][$i]);
										unset($discount_amounts[$i]);
									}
								}
							}
					}
			}
			foreach($discount_amounts as $dca){
				$discount_amount += $dca;
			}
			
			if(!empty( $data['line_items'] )){
				$flag = true;
				foreach($data['line_items'] as $key => $line_item){
					if(!empty( $line_item['variation_id'] )){
						$product = wc_get_product( intval( $line_item['product_id'] ) );
						if(method_exists( $product, 'get_available_variations' )){
							$found = false;
							$variation_id = intval( $line_item['variation_id'] );
							$variations = $product->get_available_variations('array');
							foreach($variations as $variation){
								if($variation_id === $variation['variation_id']){
									$found = true;
									break;
								}
							}
							
							if($found === false){
								$message = sprintf( __( 'The provided variation "%d" is not a valid variation of the product "%d"', 'terminal-for-stripe-and-woocommerce' ), $variation_id, $line_item['product_id'] );
								return new WP_Error( 'invalid_variation', $message, array( 'status' => 400 ) );
							}

							$data['line_items'][$key]['product_id'] = $variation_id;
						}

						unset( $data['line_items'][$key]['variation_id'] );
					}
				}
			}else{
				$data['line_items'] = array();
			}
			foreach(['billing','shipping'] as $typer){
				if(isset($data[$typer.'_address'])){
					foreach($data[$typer.'_address'] as $bill_key => $bill_prop){
						if($bill_key == 'country' && (empty($bill_prop) || $bill_prop == 'c')){
							$data[$typer.'_address']['country'] = 'US';
						}
						if($bill_prop===null){
							$data[$typer.'_address'][$bill_key] = '';
						}
						/*$fxn = 'set_'.$typer.'_'.$bill_key;
						if(method_exists( $order, $fxn)){
							$order->$fxn($bill_prop);
						}*/
					}
				}
			}
			$data = array( 'order' => $data );
		}

		$order_api = new WC_API_Orders( new WC_API_Server( '/' ) );
		$response = $order_api->edit_order( $order->get_id(), $data );
		if($response instanceof WP_Error || empty($response) || empty($response['order'])){
			return $response;
		}

		$order = wc_get_order( $response['order']['id'] );
		
		if(!empty($order->get_items())){
			$status = $order->get_status();
			$order_items = $order->get_items();
		}
		
		$oid = $order->get_id();
		
		$order = self::order_calculate($oid, $discount_amount);
		
		$user = $user_id = null;
		
		$tfsw_settings = get_option('woocommerce_stripe_terminal_settings');
		$skip_order_storage = ($tfsw_settings[ 'ignore_lookup' ] && $tfsw_settings[ 'ignore_lookup' ] == 'yes')? true : false;
		
		if(isset($data['order']['billing_address']) && !empty($data['order']['billing_address']['email'])){	
			$user = get_user_by( 'email',  $data['order']['billing_address']['email'] );
			if(!empty($user)){
				$user_id = $user->ID;
				$customer = new WC_Customer($user_id);
				// If we have no billing details on the order but the customer does have billing details, set it to the order
				foreach(['billing','shipping'] as $typer){
					foreach(['first_name','last_name','address_1','phone','country'] as $key){
						$fnx = 'get_'.$typer.'_'.$key;
						$fnx_2 = 'set_'.$typer.'_'.$key;
						if(empty($data['order'][$typer.'_'.$key]) && method_exists($order, $fnx_2) && method_exists($customer, $fnx) && empty($order->$fnx()) && !empty($customer->$fnx())){
							//$data['order'][$typer.'_'.$key] = $value;
							$value = $customer->$fnx();
							if(empty($skip_order_storage)){
								$order->$fnx_2($value);
							}
						}
					}
				}
				foreach(['billing','shipping'] as $typer){
					$dependent_address_array = [];
					foreach(['city','state','postcode'] as $key){
						$fnx = 'get_'.$typer.'_'.$key;
						$fnx_2 = 'set_'.$typer.'_'.$key;
						if(empty($data['order'][$typer.'_'.$key]) && method_exists($customer, $fnx) && empty($order->$fnx()) && !empty($customer->$fnx())){
							$value = $customer->$fnx();
							if(empty($skip_order_storage)){
								$dependent_address_array[$key] = $value;
							}
						}
					}
					// If all 3 of the dependent address variables are missing.  Why do we do this?  City/State combo and Postcode are interchangeable, so it MAY be that they could POST city/state and not zip
					// So we do not want to overwrite zip with the wrong zip.  We must only auto-post if all 3 are missing
					if(count($dependent_address_array) == 3){
						foreach($dependent_address_array as $key=>$value){
							if(method_exists($order, $fnx_2)){
								//$data['order'][$typer.'_'.$key] = $value;
								$fnx_2 = 'set_'.$typer.'_'.$key;
								if(empty($skip_order_storage)){
									$order->$fnx_2($value);
								}
							}
						}
					}
				}
			} else if ($tfsw_settings[ 'create_account' ] && $tfsw_settings[ 'create_account' ] == 'yes' ){
				$user_id = wp_create_user(sanitize_text_field( $data['order']['billing_address']['email'] ), str_shuffle('ABCDEFGHabcdefgh'), sanitize_text_field( $data['order']['billing_address']['email'] ));
			}
			$order->set_billing_email(sanitize_text_field( $data['order']['billing_address']['email'] ));
			if(!empty($user_id)){
				update_post_meta($order->get_id(), '_customer_user', $user_id);
			}
		}
		
		$order->save();
		
		if(empty($user_id) && null !== ($email_preference = $order->get_billing_email()) && !empty($email_preference)){
			$user = get_user_by( 'email',  $email_preference );
			$user_id = $user->ID;
		}
		
		if(!empty($user_id)){
			self::setCustomerDetails($user_id,$order);
		}
		
		if($flag == false && !empty($status) && ($status == 'completed' || $status == 'processing')){
			$st_settings = get_option( 'woocommerce_stripe_terminal_settings' );
			if($status=='completed' && 'no' !== $st_settings['complete_status']){
				$order->set_status('processing');
				$order = self::order_calculate($oid, $discount_amount);
			}
			foreach ( $order_items as $item_id => $item ) {
				$product_id = $item->get_product_id();
				$product = $item->get_product();
				$quantity = (null !== $item->get_quantity() && false !== $item->get_quantity())? $item->get_quantity() : 1;
				$is_subscription = false;
				// Before we can even bother checking on the subscription, we need to be sure this is a subscription type
				if(in_array( $product->get_type(), ['subscription', 'subscription_variation'] )){
					$is_subscription = true;
					if($product->get_type() == 'subscription_variation'){
						// change the product ID to the variation id.
						$product_id = $item->get_variation_id();
						$product = wc_get_product($product_id);
					}
				}
				if(!$is_subscription && !empty($product->get_meta_data())){
					$is_subscription = array_search('_subscription_period', array_column($product->get_meta_data(), 'key'));		
				}
				if($is_subscription){
					if(!empty($email_preference)){
						// No user exists, so we need to create one.
						if(empty($user_id)){
							$user_id = wp_create_user($email_preference, str_shuffle('ABCDEFGHabcdefgh'), $email_preference);
							self::setCustomerDetails($user_id,$order);
						}
						if(!empty($user_id)){
							// Now that we know that a user exists, the subscription should be created.  You might think that creating an order with a subscription would do this but no, that's not the case.
							// This is where things get a bit complicated because subscriptions are a 3rd party plugin construct
							if(! function_exists( 'wcs_create_subscription' ) || ! class_exists( 'WC_Subscriptions_Product' ) ){
								return new WP_Error( 'invalid_variation', 'This order completed successfully but it contains a subscription product and your website does not have the subscription creation function, so it could not be set up.', array( 'status' => 403 ) );
							} else {
								$sub = wcs_create_subscription(array(
									'order_id' => $oid,
									'status' => 'pending',
									'billing_period' => WC_Subscriptions_Product::get_period( $product_id ),
									'billing_interval' => WC_Subscriptions_Product::get_interval( $product_id ),
									'customer_id' => $user_id
								));
								if( is_wp_error( $sub ) ){
									return new WP_Error( 'invalid_variation', 'This order completed successfully but the subscription(s) failed. Please set up on your website.', array( 'status' => 403 ) );
								}

								// Modeled after WC_Subscriptions_Cart::calculate_subscription_totals()
								$start_date = gmdate( 'Y-m-d H:i:s' );
								// Add product to subscription
								$sub->add_product( $product, $quantity );

								$dates = array(
									'trial_end'    => WC_Subscriptions_Product::get_trial_expiration_date( $product_id, $start_date ),
									'next_payment' => WC_Subscriptions_Product::get_first_renewal_payment_date( $product_id, $start_date ),
									'end'          => WC_Subscriptions_Product::get_expiration_date( $product_id, $start_date ),
								);

								$sub->update_dates( $dates );
								$sub->calculate_totals();

								$note = ! empty( $data['note'] ) ? $data['note'] : __( 'Programmatically added order and subscription from ArcanePOS.  Recurring payments done with card present may need to be manually entered.' );

								$sub->update_status( 'active', $note, true );
							}
						} else {
							return new WP_Error( 'invalid_variation', 'This order completed successfully but the subscription(s) failed due to lack of customer account information. Please set up on your website.', array( 'status' => 403 ) );
						}
					} else {
						return new WP_Error( 'invalid_variation', 'This order completed successfully but the subscription(s) failed due to lack of customer account information. Please set up on your website.', array( 'status' => 403 ) );
					}
				}
			}
		}
		$order = self::order_calculate($oid, $discount_amount);
		return $order_api->get_order( $order->get_id() );
	}

	/**
	 * Deletes an existing order
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	public function delete_order(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}

		$order = wc_get_order( intval( $request->get_param( 'id' ) ) );
		if(empty( $order )){
			return new WP_Error( 'order_not_found', __( 'We could not find an order with the given order id.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 404 ) );
		}

		$order->delete( true );
		return rest_ensure_response( 'deleted' );
	}

	/**
	 * Gets all available shipping methods
	 *
	 * @since 1.1.4
	 * @version 1.1.4
	 */
	public function shipping_methods(WP_REST_Request $request){
		$token_valid = $this->validate_token( $request );
		if($token_valid !== true){
			return $token_valid;
		}

		$order = wc_get_order( intval( $request->get_param( 'id' ) ) );
		if(empty( $order )){
			return new WP_Error( 'order_not_found', __( 'We could not find an order with the given order id.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 404 ) );
		}

		$order_data = $order->get_data();
		if(empty( $order_data ) || empty( $order_data['shipping'] ) || empty( $order_data['shipping']['address_1'] )){
			return new WP_Error( 'no_shipping_address', __( 'You must set a shipping address to the order before retrieving shipping methods.', 'terminal-for-stripe-and-woocommerce' ), array( 'status' => 400 ) );
		}

		if( WC()->cart ){
			WC()->cart->empty_cart();
		}

		$shipping = $order_data['shipping'];
		$line_items = $order->get_items( 'line_item' );
		
		if ( defined( 'WC_ABSPATH' ) ) {
			// WC 3.6+ - Cart and other frontend functions are not included for REST requests.
			include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
			include_once WC_ABSPATH . 'includes/wc-notice-functions.php';
			include_once WC_ABSPATH . 'includes/wc-template-hooks.php';
		}

		if ( null === WC()->session ) {
			$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
			WC()->session = new $session_class();
			WC()->session->init();
		}

		WC()->cart = new SFTW_Cart($shipping);
		foreach($line_items as $item){
			$product = $item->get_product();
			$qty = (null !== $item->get_quantity() && false !== $item->get_quantity())? $item->get_quantity() : 1;
			$variant = !empty($item->get_variation_id())? $item->get_variation_id() : null;
			//$id = $product->id;
			$id = $item->get_product_id();
			WC()->cart->add_to_cart($id, $qty, $variant);
		}

		$shipping = new WC_Shipping();
		$package = $shipping->calculate_shipping_for_package( WC()->cart->get_shipping_packages()[0] );
		$available_methods = array();
		if(!empty($package['rates'])){
			foreach($package['rates'] as $rate){
				$available_methods[] = array(
					'method_id' => $rate->get_id(),
					'method_title' => $rate->get_label(),
					'total' => $rate->get_cost()
				);
			}
		}

		// Cleanup
		WC()->cart = null;
		wc_empty_cart();
		return new WP_REST_Response( $available_methods );
	}
}

class SFTW_Cart extends WC_Cart{
	private $customer;

	public function __construct($shipping){
		parent::__construct();
		$this->customer = new WC_Customer();
		if(!empty($shipping['first_name'])){
			$this->customer->set_shipping_first_name($shipping['first_name']);
		}
		if(!empty($shipping['last_name'])){
			$this->customer->set_shipping_last_name($shipping['last_name']);
		}
		if(!empty($shipping['company'])){
			$this->customer->set_shipping_company($shipping['company']);
		}
		if(!empty($shipping['address_1'])){
			$this->customer->set_shipping_address_1($shipping['address_1']);
		}
		if(!empty($shipping['address_2'])){
			$this->customer->set_shipping_address_2($shipping['address_2']);
		}
		if(!empty($shipping['city'])){
			$this->customer->set_shipping_city($shipping['city']);
		}
		if(!empty($shipping['state'])){
			$this->customer->set_shipping_state($shipping['state']);
		}
		if(!empty($shipping['postcode'])){
			$this->customer->set_shipping_postcode($shipping['postcode']);
		}
		if(!empty($shipping['country'])){
			$this->customer->set_shipping_country($shipping['country']);
		}
	}

	public function get_customer() {
		return $this->customer;
	}
}