<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'tfsw_stripe_terminal_settings',
	array(
		'enabled'     => array(
			'title'       => __( 'Enable/Disable', 'terminal-for-stripe-and-woocommerce' ),
			'label'       => __( 'Enable Stripe Terminal', 'terminal-for-stripe-and-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'complete_status'     => array(
			'title'       => __( 'Completed Mobile Order Status', 'terminal-for-stripe-and-woocommerce' ),
			'label'       => __( 'Set Mobile Order Status to Processing', 'terminal-for-stripe-and-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'When a mobile order is processed, instead of storing it as Completed we will store as Processing.  Web based orders are handled by the WooCommerce payment_complete method, which controls this decision.  To make that change for web-based orders, consult your WooCommerce settings.', 'terminal-for-stripe-and-woocommerce' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'mobile_transaction'     => array(
			'title'       => __( 'Mobile App Button', 'terminal-for-stripe-and-woocommerce' ),
			'label'       => __( 'Display Mobile App Button', 'terminal-for-stripe-and-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'When checking out, a button will display to allow you to open the order in our mobile app for mobile readers.', 'terminal-for-stripe-and-woocommerce' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'stripe_connect_id' => array(
			'title'       => __( 'Stripe Account ID', 'terminal-for-stripe-and-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Your Stripe ID as seen in your Stripe.com Account.  This begins with "acct_", it is not your username.', 'terminal-for-stripe-and-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'title'       => array(
			'title'       => __( 'Title', 'terminal-for-stripe-and-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'terminal-for-stripe-and-woocommerce' ),
			'default'     => __( 'Terminal', 'terminal-for-stripe-and-woocommerce' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'terminal-for-stripe-and-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'terminal-for-stripe-and-woocommerce' ),
			'default'     => '',
		),
		'connect'        => array(
			'title'	      => __('Activate Terminal Payments', 'terminal-for-stripe-and-woocommerce'),
			'type'        => 'text',
			'description' => __('Required: Connect your Stripe account to accept payments.  Stripe Account ID must be completed above as well.'),
			'default'     => '',
		),
		'customers'        => array(
			'title'	      => __('Subscription & Customer Data Storage', 'terminal-for-stripe-and-woocommerce'),
			'type'        => 'text',
			'description' => __('Support for subscriptions, virtual products, digital downloads, and customer data storage unavailable in this plugin.<br/>To enable use of customer accounts tied to order data as well as customer data sync between your site and Stripe.com, you must purchase pro services.', 'terminal-for-stripe-and-woocommerce'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'fleet'        => array(
			'title'	      => __('Fleet Management', 'terminal-for-stripe-and-woocommerce'),
			'type'		  => 'hidden',
			'description' => __('Manage your readers across locations to manage security and track which employee, reader, and location made each purchase within your Stripe.com dashboard.'),
		),
		'location'        => array(
			'title'	      => __('Locations', 'terminal-for-stripe-and-woocommerce'),
			'type'        => 'text',
			'description' => __('Use of multiple Stripe locations unavailable in this plugin. For locations management, please purchase pro services.', 'terminal-for-stripe-and-woocommerce'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'readersl'       => array(
			'title'	      => __('Web Readers', 'terminal-for-stripe-and-woocommerce'),
			'type'        => 'text',
			'description' => __('To register a new reader, connect it to the internet and enter 0-7-1-3-9 into the keypad. Then click this button and enter the passphrase from your terminal. You will see a list of connected readers displayed here.  The free plugin only supports 1 reader.  To manage fleets, purchase pro services.', 'terminal-for-stripe-and-woocommerce'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'subscribe'     => array(
			'title'       => __( 'Subscribe to Newsletter', 'terminal-for-stripe-and-woocommerce' ),
			'label'       => __( 'Get notified of feature updates and discounted add-ons in advance', 'terminal-for-stripe-and-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
	)
);
