<?php

namespace PW\PWSMS;

defined( 'ABSPATH' ) || exit;

use PW\PWSMS\API\ContentAPI;
use PW\PWSMS\API\SMSAPI;
use PW\PWSMS\Product\Tab as ProductTab;
use PW\PWSMS\Services\APIService;
use PW\PWSMS\Services\EventService;
use PW\PWSMS\Services\ScheduleService;
use PW\PWSMS\Settings\Settings;
use PW\PWSMS\SMS\Archive;
use PW\PWSMS\Subscription\Contacts;
use PW\PWSMS\API\SubscriptionAPI;
use PW\PWSMS\Subscription\Widget;
use PW\PWSMS\Vendor\Vendors;

class PWSMS {

	private static ?PWSMS $instance = null;

	public static function instance(): PWSMS {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		new Settings();
		new Bulk();
		new About();
		new Promotions();
		new Notice();
		new MetaBox();
		new ProductTab();
		new Orders();
		new Archive();
		new Contacts();
		new Vendors();
		new ChangeLog();

		new EventService();
		new APIService();
		new ScheduleService();

		$this->init();
	}

	public function init() {
		add_action( 'widgets_init', [ $this, 'register_widget' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_style' ] );
		add_action( 'plugin_action_links_' . plugin_basename( PWSMS_FILE ), [ $this, 'action_links' ] );
	}

	public function register_widget() {
		$widget = new Widget();
		register_widget( $widget );
	}

	public function admin_style( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->id, [
				'%d9%88%d9%88%da%a9%d8%a7%d9%85%d8%b1%d8%b3-%d9%81%d8%a7%d8%b1%d8%b3%db%8c_page_persian-woocommerce-sms-pro',
				'admin_page_persian-woocommerce-sms-pro',
				'admin_page_pwsms-changelog',
				'product',
				'woocommerce_page_wc-orders',
				'shop_order',
			], true ) ) {
			return;
		}

		$min = wp_scripts_get_suffix();

		wp_enqueue_style( 'pwsms_admin_style', esc_url( PWSMS_URL . '/assets/css/admin' . $min . '.css' ), [], PWSMS_VERSION );
		wp_register_script( 'pwsms-sweetalert2', PWSMS_URL . '/assets/js/sweetalert2.all.min.js', [], PWSMS_VERSION, true );
		wp_register_style( 'pwsms-sweetalert2', PWSMS_URL . '/assets/css/sweetalert2.min.css', [], PWSMS_VERSION );
	}

	public function action_links( array $links ): array {

		$actions['settings'] = sprintf( '<a href="%s" target="blank">%s</a>', admin_url( 'admin.php?page=persian-woocommerce-sms-pro' ), 'تنظیمات' );

		$brand = [
			'woo_ir' => sprintf( '<a href="%s" target="blank" style="background: #763ec2;color: white;padding: 0px 5px;border-radius: 2px;">%s</a>', 'https://woocommerce.ir', 'ووکامرس فارسی' ),
		];

		return $brand + $actions;
	}
}
