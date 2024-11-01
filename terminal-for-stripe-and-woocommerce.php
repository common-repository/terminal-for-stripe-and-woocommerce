<?php
/**
 * Plugin Name: Terminal for Stripe and WooCommerce
 * Plugin URI: https://www.arcanestrategies.com/products/stripe-terminal-for-woocommerce
 * Description: Take credit card payments locally on your store using Stripe Terminal.
 * Author: Arcane Strategies
 * Author URI: https://www.arcanestrategies.com/
 * Version: 2.2.3
 * Requires at least: 5.5.0
 * Tested up to: 5.9.2
 * WC requires at least: 5.1.0
 * WC tested up to: 6.3.1
 * Text Domain: terminal-for-stripe-and-woocommerce
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 4.1.2
 * @return string
 */

if(!defined('TFSW_SLUG')){
	define('TFSW_SLUG', 'terminal-for-stripe-and-woocommerce');
}

register_activation_hook(__FILE__, 'tfsw_activate');
add_action( 'admin_notices', 'get_tfsw_notice' ); 
add_action('plugins_loaded', 'tfsw_init',99);

function get_tfsw_notice() {
	$alert = get_tfsw_promotion(['keyword' => ['terminal'],'version' => (defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '2.2.3')]);
	if($alert['success']){
		$alert = $alert['success'];
		preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $alert, $match);
		if(!empty($match[0])){
			foreach($match[0] as $m){
				$alert = str_replace($m,'<a target="_blank" class="wc-update-now button-primary" href="'.$m.'">'.$m.'</a>',$alert);
			}
		}
		if ( '' !== trim( $alert ) ) {
			echo '<div class="notice notice-warning"><p>'.$alert.'</p></div>';
		}
	}
}

function get_tfsw_promotion(array $body){
	$api_url = 'https://store.arcanestrategies.com/api/promotion';
	$domain = get_site_url();
	$body['domain'] = $domain;
	$response = wp_remote_post($api_url , array(
		'body' => json_encode($body),
		'headers' => array('Content-Type' => 'application/json'),
	));
	if (!($response instanceof WP_Error) && $response['response']['code'] == 200) {
		try {
			return json_decode($response['body'], true);
		}
		catch (\Exception $e) {
		}	
	}
}

function tfsw_activate(){
	if(!function_exists('is_plugin_active_for_network')){
		include_once(ABSPATH . '/wp-admin/includes/plugin.php');
	}

	if(current_user_can('activate_plugins') && (!class_exists('WooCommerce') ||
		!is_plugin_active('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php') ||
		!file_exists(dirname(dirname( __FILE__ )) . '/woocommerce-gateway-stripe/includes/class-wc-stripe-intent-controller.php'))){
		// Deactivate the plugin.
		deactivate_plugins(plugin_basename( __FILE__ ));
		$error_message = sprintf(esc_html__('Stripe Terminal requires WooCommerce to be installed and active. You can download %s here.', 'terminal-for-stripe-and-woocommerce'), '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>');
		if(!is_plugin_active('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php') || !file_exists(dirname(dirname( __FILE__ )) . '/woocommerce-gateway-stripe/includes/class-wc-stripe-intent-controller.php')){
			$error_message = sprintf( esc_html__( 'Stripe Termninal requires WooCommerce Stripe to be installed and active, with at least version 4.2. You can download %s here.', 'terminal-for-stripe-and-woocommerce'), '<a href="https://wordpress.org/plugins/woocommerce-gateway-stripe/" target="_blank">WooCommerce Stripe</a>' );
		}
		wp_die($error_message);
	}
}

function tfsw_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Stripe Terminal requires WooCommerce to be installed and active. You can download %s here.', 'terminal-for-stripe-and-woocommerce'), '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

function tfsw_missing_wcs_notice(){
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Stripe Termninal requires WooCommerce Stripe to be installed and active, with at least version 4.2. You can download %s here.', 'terminal-for-stripe-and-woocommerce'), '<a href="https://wordpress.org/plugins/woocommerce-gateway-stripe/" target="_blank">WooCommerce Stripe</a>' ) . '</strong></p></div>';
}

function tfsw_pro_exists(){
	echo '<div class="error"><p><strong>' . __( 'Stripe Terminal can not be activated while the pro version of this plugin is activated.', 'terminal-for-stripe-and-woocommerce') . '</strong></p></div>';
}

function tfsw_lite_exists(){
	echo '<div class="error"><p><strong>' . __( 'Stripe Terminal can not be activated while the lite version of this plugin is activated.', 'terminal-for-stripe-and-woocommerce') . '</strong></p></div>';
}

function tfsw_init() {
	load_plugin_textdomain( 'terminal-for-stripe-and-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	include_once(ABSPATH . '/wp-admin/includes/plugin.php');

	if(!class_exists('WooCommerce')){
		add_action( 'admin_notices', 'tfsw_missing_wc_notice' );
		return;
	}

	if(!is_plugin_active('woocommerce-gateway-stripe/woocommerce-gateway-stripe.php') || !file_exists(dirname(dirname( __FILE__ )) . '/woocommerce-gateway-stripe/includes/class-wc-stripe-intent-controller.php')){
		add_action( 'admin_notices', 'tfsw_missing_wcs_notice' );
		return;
	}

	if(class_exists('Tfsw_Stripe_Terminal_Pro')){
		add_action( 'admin_notices', 'tfsw_pro_exists' );
		return;
	}
	
	if(class_exists('Tfsw_Stripe_Terminal_Lite')){
		add_action( 'admin_notices', 'tfsw_lite_exists' );
		return;
	}

	if ( ! class_exists('Tfsw_Stripe_Terminal') ) :
		/**
		 * Required minimums and constants
		 */
		define( 'WC_STRIPE_TERMINAL_VERSION', '2.2.3' );
		define( 'WC_STRIPE_TERMINAL_MIN_PHP_VER', '7.3.0' );
		define( 'WC_STRIPE_TERMINAL_MIN_WC_VER', '5.1.0' );
		define( 'WC_STRIPE_TERMINAL_MAIN_FILE', __FILE__ );
		define( 'WC_STRIPE_TERMINAL_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'WC_STRIPE_TERMINAL_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

		class Tfsw_Stripe_Terminal {

			/**
			 * @var Singleton The reference the *Singleton* instance of this class
			 */
			private static $instance;

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
				add_action( 'admin_init', array( $this, 'install' ) );
				$this->init();
			}
			
			public function traverse($directory){
				$scanned_directory = array_diff(scandir(dirname(dirname( __FILE__ )) . '/'.$directory.'/'), array('..', '.'));
				$scanned_directory = array_values($scanned_directory);
				$results = [];
				if(!empty($scanned_directory)){
					for($i=0; $i<count($scanned_directory); $i++){
						if(false==strpos($scanned_directory[$i],'php')){
							$subdirectory = $directory.'/'.$scanned_directory[$i];
							$subdirectory = self::traverse($subdirectory);
							$results = array_merge($results,$subdirectory);
						} else {
							$results[] = dirname(dirname( __FILE__ )) . '/'.$directory.'/'.$scanned_directory[$i];
						}
					}
				}
				return array_values($results);
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function init(){
				require_once dirname(__FILE__) . '/includes/tfsw-stripe-terminal-api.php';
				require_once dirname(__FILE__) . '/includes/tfsw-api.php';				
				require_once dirname(__FILE__) . '/includes/payment-methods/tfsw-gateway-stripe-terminal.php';
				require_once dirname(__FILE__) . '/includes/admin/tfsw-stripe-terminal-ajax.php';				

				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
				add_filter( 'wc_products_apply_get_data', array( $this, 'products_apply_get_data' ) );
				add_action( 'wc_gateway_stripe_process_response', array( $this, 'store_fingerprint' ) );
				
				add_filter( 'wordfence_ls_require_captcha', '__return_false' );

				if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
					add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_gateway_order_admin' ) );
				}

				WC_TFSW_API::get_instance();
			}

			/**
			 * Updates the plugin version in db
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function update_plugin_version() {
				delete_option( 'wc_stripe_terminal_version' );
				update_option( 'wc_stripe_terminal_version', (defined('WC_STRIPE_TERMINAL_VERSION')? WC_STRIPE_TERMINAL_VERSION : '1.0.0') );
			}

			/**
			 * Handles upgrade routines.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function install() {
				if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					return;
				}

				if ( ! defined( 'IFRAME_REQUEST' ) && defined('WC_STRIPE_TERMINAL_VERSION') && ( WC_STRIPE_TERMINAL_VERSION !== get_option( 'wc_stripe_terminal_version' ) ) ) {
					do_action( 'woocommerce_stripe_terminal_updated' );

					if ( ! defined( 'TFSW_STRIPE_TERMINAL_INSTALLING' ) ) {
						define( 'TFSW_STRIPE_TERMINAL_INSTALLING', true );
					}

					$this->update_plugin_version();
				}
			}

			/**
			 * Adds plugin action links.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function plugin_action_links( $links ) {
				$plugin_links = array(
					'<a href="admin.php?page=wc-settings&tab=checkout&section=stripe_terminal">' . esc_html__( 'Settings', 'terminal-for-stripe-and-woocommerce' ) . '</a>'
				);
				return array_merge( $plugin_links, $links );
			}

			/**
			 * Add the gateways to WooCommerce.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function add_gateways( $methods ) {
				$methods[] = 'Tfsw_Gateway_Stripe_Terminal';
				return $methods;
			}

			/**
			 * Modifies the order of the gateways displayed in admin.
			 *
			 * @since 1.0.0
			 * @version 1.0.0
			 */
			public function filter_gateway_order_admin( $sections ) {
				$sections['stripe_terminal'] = __( 'Stripe Terminal', 'terminal-for-stripe-and-woocommerce' );

				return $sections;
			}

			/**
			 * Stores the stripe card fingerprint.
			 *
			 * @since 1.1.0
			 * @version 1.1.3
			 */
			public function store_fingerprint( $stripe_response=null, $order=null ) {
				if(null===$order||$stripe_response===null){
					return;
				}

				if(!empty($pm_details) && !empty($pm_details->card) && !empty($pm_details->card->fingerprint)){
					$order->update_meta_data('_stripe_pm_fingerprint', $pm_details->card->fingerprint);
					$order->save();
				}else if(!empty($pm_details) && !empty($pm_details->card_present) && !empty($pm_details->card_present->fingerprint)){
					$order->update_meta_data('_stripe_pm_fingerprint', $pm_details->card_present->fingerprint);
					$order->save();
				}
			}

			/**
			 * Applies the get_data function to objects with that method for easily json serialization.
			 *
			 * @since 1.1.4
			 * @version 1.1.4
			 */
			public function products_apply_get_data($products){
				if(is_array($products)){
					$new_products = array();
					foreach($products as $product){
						if(is_object($product) && method_exists($product, 'get_data')){
							$data = $product->get_data();
							$data['has_variants'] = method_exists( $product, 'get_available_variations' );
							if(isset($data['image_id'])){
								$data['image_url'] = '';
								if(null !== ($image_id = $product->get_image_id())){
									$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
									$data['image_url'] = $image_url;
								}
 							}
							
							$new_products[] = $data;
						}else{
							if(is_object($product)){
								$temp_product = (array)$product;
							}
							if(isset($temp_product['image_id'])){
								$product = $temp_product;
								$product['image_url'] = '';
								if(null !== ($image_id = $product['image_id'])){
									$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
									$data['image_url'] = $image_url;
								}
 							}
							$new_products[] = $product;
						}
					}

					return $new_products;
				}else if(is_object($products)){
					if(method_exists($products, 'get_data')){
						$data = $products->get_data();
						$data['has_variants'] = method_exists( $product, 'get_available_variations' );
						return $data;
					}else if(property_exists($products, 'products') && is_array($products->products)){
						$products->products = apply_filters('wc_products_apply_get_data', $products->products);
						if(property_exists($products, 'total') && is_string($products->total) && (intval($products->total) . '') === $products->total){
							$products->total = intval($products->total); // Normalize this to an integer while we're at it...
						}

						return $products;
					}
				}

				return $products;
			}
		}

		Tfsw_Stripe_Terminal::get_instance();
	endif;
}
