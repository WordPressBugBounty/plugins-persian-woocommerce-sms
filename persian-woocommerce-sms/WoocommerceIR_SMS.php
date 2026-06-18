<?php
/**
 * Plugin Name: پیامک حرفه ای ووکامرس
 * Plugin URI: https://wordpress.org/plugins/persian-woocommerce-sms
 * Description: افزونه کامل و حرفه ای برای اطلاع رسانی پیامکی سفارشات و رویداد های محصولات ووکامرس. تمامی حقوق این افزونه متعلق به <a href="http://woosupport.ir" target="_blank">تیم ووکامرس فارسی</a> می باشد و هر گونه کپی برداری، فروش آن غیر مجاز می باشد.
 * Version: 7.2.1
 * Author: ووکامرس فارسی
 * Author URI: https://woosupport.ir
 *
 * License URI:  https://www.gnu.org/licenses/gpl-3.0.html
 * License:      GPLv3
 *
 * WC requires at least: 7.6.0
 * WC tested up to: 10.8.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'PWSMS_VERSION' ) ) {
	define( 'PWSMS_VERSION', '7.2.1' );
}

if ( ! defined( 'PWSMS_DIR' ) ) {
	define( 'PWSMS_DIR', __DIR__ );
}

if ( ! defined( 'PWSMS_FILE' ) ) {
	define( 'PWSMS_FILE', __FILE__ );
}

if ( ! defined( 'PWSMS_URL' ) ) {
	define( 'PWSMS_URL', plugin_dir_url( __FILE__ ) );
}

require __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, 'PWSMS_REGISTER' );
register_deactivation_hook( __FILE__, 'PWSMS_REGISTER' );

function PWSMS_REGISTER() {
	delete_option( 'pwoosms_table_archive' );
	delete_option( 'pwoosms_table_contacts' );
	delete_option( 'pwoosms_hide_about_page' );
	delete_option( 'pwoosms_redirect_about_page' );
}

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Rollback support for general shortcode function
 */
if ( ! function_exists( 'pwsms_shortcode' ) ) {
	function pwsms_shortcode( $get = false, $strip_brackets = false ) {
		if ( $get ) {
			return PW\PWSMS\Shortcode::shortcode( $get, $strip_brackets );
		}
		PW\PWSMS\Shortcode::shortcode( $get, $strip_brackets );
	}
}

/**
 * Helper instance is the whole functions to interact with core or gateways
 *
 * @return PW\PWSMS\Helper
 * */
if ( ! function_exists( 'PWSMS' ) ) {
	function PWSMS() {
		return PW\PWSMS\Helper::instance();
	}
}

/**
 * Rollback support for PWSMS function,
 * This function is used in other plugins
 *
 * @return PW\PWSMS\Helper
 */
if ( ! function_exists( 'PWooSMS' ) ) {
	function PWooSMS() {
		return PWSMS();
	}
}

/**
 * Run whole system
 */
if ( class_exists( '\PW\PWSMS\PWSMS' ) ) {
	PW\PWSMS\PWSMS::instance();
}