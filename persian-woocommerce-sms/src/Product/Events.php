<?php

namespace PW\PWSMS\Product;

defined( 'ABSPATH' ) || exit;

class Events {

	private CustomerEvents $customer_events;
	private AdminEvents $admin_events;

	public function __construct() {
		$this->customer_events = new CustomerEvents();
		$this->admin_events    = new AdminEvents();

		add_action( 'init', [ $this, 'init_admin' ] );

		if ( PWSMS()->get_option( 'enable_notif_sms_main' ) ) {
			add_action( 'init', [ $this, 'init_user' ] );
		}
	}

	public function init_admin() {
		$action = ! empty( $_POST['action'] ) ? str_ireplace( 'woocommerce_', '', sanitize_text_field( $_POST['action'] ) ) : '';

		if ( in_array( $action, [ 'add_variation', 'link_all_variations' ] ) ) {
			return;
		}

		/*outStock*/
		add_action( 'woocommerce_product_set_stock_status', [ $this->admin_events, 'out_of_stock' ], 12, 3 );
		add_action( 'woocommerce_variation_set_stock_status', [ $this->admin_events, 'out_of_stock' ], 12, 3 );

		/*lowStock*/
		add_action( 'woocommerce_low_stock', [ $this->admin_events, 'low_stock' ] );
		add_action( 'woocommerce_product_set_stock', [ $this->admin_events, 'low_stock' ] );
		add_action( 'woocommerce_variation_set_stock', [ $this->admin_events, 'low_stock' ] );
	}

	public function init_user() {
		$action = ! empty( $_POST['action'] ) ? str_ireplace( 'woocommerce_', '', sanitize_text_field( $_POST['action'] ) ) : '';

		if ( in_array( $action, [ 'add_variation', 'link_all_variations' ] ) ) {
			return;
		}

		/*onSale*/
		add_action( 'woocommerce_process_product_meta', [ $this->customer_events, 'onsale' ], 9999, 1 );
		add_action( 'woocommerce_update_product_variation', [ $this->customer_events, 'onsale' ], 9999, 1 );
		add_action( 'woocommerce_sms_send_onsale_event', [ $this->customer_events, 'onsale' ] );

		/*inStock*/
		add_action( 'woocommerce_product_set_stock_status', [ $this->customer_events, 'in_stock' ], 12, 3 );
		add_action( 'woocommerce_variation_set_stock_status', [ $this->customer_events, 'in_stock' ], 12, 3 );

		/*lowStock*/
		add_action( 'woocommerce_low_stock', [ $this->customer_events, 'low_stock' ] );
		add_action( 'woocommerce_product_set_stock', [ $this->customer_events, 'low_stock' ] );
		add_action( 'woocommerce_variation_set_stock', [ $this->customer_events, 'low_stock' ] );

		add_action( 'shutdown', [ $this->customer_events, 'bulk_remove_contacts_groups' ] );
	}
}
