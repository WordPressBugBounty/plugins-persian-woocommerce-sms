<?php

namespace PW\PWSMS\Vendor;

use PW\PWSMS\Bulk;

defined( 'ABSPATH' ) || exit;

class Vendors {

	private string $screen_id = '%d9%88%d9%88%da%a9%d8%a7%d9%85%d8%b1%d8%b3-%d9%81%d8%a7%d8%b1%d8%b3%db%8c_page_persian-woocommerce-sms-pro';
	private string $option_name = 'pwsms_sellers_per_page';
	private string $page = 'persian-woocommerce-sms-pro';
	private string $tab = 'bulk_vendors';

	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'vendors_enqueue_script' ] );
		add_action( 'pwoosms_settings_form_bottom_sms_bulk_vendors', [ $this, 'vendors_table' ] );
		// Screen Options
		add_action( 'admin_head', [ $this, 'maybe_add_screen_option' ] );
		add_filter( 'set-screen-option', [ $this, 'save_screen_option' ], 10, 3 );
	}

	public function vendors_enqueue_script() {

		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'persian-woocommerce-sms-pro' || ! isset( $_GET['tab'] ) || $_GET['tab'] !== 'bulk_vendors' ) {
			return;
		}

		wp_enqueue_style( 'pwsms-sweetalert2' );
		wp_enqueue_script( 'pwsms-sweetalert2' );

		wp_enqueue_script( 'pwsms-vendors', PWSMS_URL . '/assets/js/vendors.js', [ 'pwsms-sweetalert2', 'jquery' ], PWSMS_VERSION, true );
		wp_localize_script( 'pwsms-vendors', 'pwsms_vendors', [
			'rest_url' => esc_url_raw( rest_url() ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'spinner'  => PWSMS_URL . '/assets/images/ajax-loader.gif',
		] );
	}

	/**
	 * Shows the dokan vendors table list
	 *
	 * @return void;
	 */
	public function vendors_table() {

		if ( ! is_plugin_active( 'dokan-lite/dokan.php' ) ) {
			echo '<p class="notice notice-warning">لطفا افزونه دکان را نصب یا فعال نمایید.</p>';

			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<p class="notice notice-warning">دسترسی شما برای مشاهده و مدیریت این صفحه کافی نیست.</p>';

			return;
		}

		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
		}

		$table = new ListTable();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">لیست فروشندگان دکان</h1>';
		echo '<hr class="wp-header-end">';
		$table->prepare_items();

		echo '<form method="get">';
		echo "<input type='hidden' name='page' value='$this->page'>";
		echo "<input type='hidden' name='tab' value='$this->tab'>";

		$table->display();
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Add screen options to the vendors table
	 *
	 * @return void
	 */
	public function maybe_add_screen_option() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== $this->page || ! isset( $_GET['tab'] ) || $_GET['tab'] !== $this->tab ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || $screen->id !== $this->screen_id ) {
			return;
		}

		add_screen_option( 'per_page', [
			'label'   => 'تعداد فروشنده در صفحه',
			'default' => 20,
			'option'  => $this->option_name
		] );
	}

	public function save_screen_option( $status, $option, $value ) {
		return ( $option === $this->option_name ) ? (int) $value : $status;
	}
}