<?php
/**
 * Plugin Name: پیامک حرفه ای ووکامرس
 * Plugin URI: https://woosupport.ir
 * Description: افزونه کامل و حرفه ای برای اطلاع رسانی پیامکی سفارشات و رویداد های محصولات ووکامرس. تمامی حقوق این افزونه متعلق به <a href="http://woosupport.ir" target="_blank">تیم ووکامرس پارسی</a> می باشد و هر گونه کپی برداری، فروش آن غیر مجاز می باشد.
 * Version: 7.0.10
 * Author: ووکامرس فارسی
 * Author URI: https://woosupport.ir
 * WC requires at least: 6.0.0
 * WC tested up to: 9.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Location: https://woosupport.ir/' );
	exit;
}

require_once 'vendor/autoload.php';

if ( ! defined( 'PWSMS_VERSION' ) ) {
	define( 'PWSMS_VERSION', '7.0.10' );
}

if ( ! defined( 'PWSMS_URL' ) ) {
	define( 'PWSMS_URL', plugins_url( '', __FILE__ ) );
}

if ( ! defined( 'PWSMS_DIR' ) ) {
	define( 'PWSMS_DIR', dirname( __FILE__ ) );
}

if ( ! defined( 'PWSMS_LOG_FILE' ) ) {
	define( 'PWSMS_LOG_FILE', wp_upload_dir()['basedir'] . '/wc-logs/pwsms.log' );
}

register_activation_hook( __FILE__, 'PWSMS_REGISTER' );
register_deactivation_hook( __FILE__, 'PWSMS_REGISTER' );

function PWSMS_REGISTER() {
	delete_option( 'pwoosms_table_archive' );
	delete_option( 'pwoosms_table_contacts' );
	delete_option( 'pwoosms_hide_about_page' );
	delete_option( 'pwoosms_redirect_about_page' );
}

/*
 * Rewrite SoapClient as a null class
 * This plugin depends on the SOAP php module
 * If the soap is not enabled, There will be an empty SoapClient class
*/
if ( ! class_exists( 'SoapClient' ) ) {
	class SoapClient {
		public function __construct( $wsdl, $options = [] ) {
		}

		public function __call( $name, $arguments ) {
			throw new Exception( "عملکرد با اشکال مواجه شد، لطفا اکستنشن SOAP را در PHP فعال کنید." );
		}
	}

	add_action( 'admin_notices', function () {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php _e( ' برای عملکرد صحیح افزونه <b>پیامک حرفه ای ووکامرس</b>، اکستنشن <b>SOAP</b> را در PHP فعال کنید.' ); ?></p>
		</div>
		<?php
	} );
}


add_action( 'before_woocommerce_init', function () {
	if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_filter( 'plugin_row_meta', function ( $links, $file ) {
	if ( strpos( $file, basename( __FILE__ ) ) ) {
		$links[] = '<a style="font-weight:bold;color:red;" href="https://hits.ir/sms-pro" target="_blank" title="پشیتبانی افزونه"> پشتیبانی PRO </a>';
		$links[] = '<a style="font-weight:bold;color:blue;" href="https://profiles.wordpress.org/persianscript/#content-plugins" target="_blank" title="مخزن وردپرس"><strong>سایر افزونه ها</strong></a>';
	}

	return $links;
}, 10, 2 );

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