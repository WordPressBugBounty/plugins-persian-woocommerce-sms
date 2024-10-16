<?php

namespace PW\PWSMS;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use PW\PWSMS\Gateways\GatewayInterface;
use PW\PWSMS\Gateways\Logger;
use PW\PWSMS\Settings\Settings;
use PW\PWSMS\Product\Events as ProductEvents;
use PW\PWSMS\Product\Tab as ProductTab;
use PW\PWSMS\SMS\Archive;
use PW\PWSMS\Subscribe\Contacts;
use PW\PWSMS\Subscribe\Widget;
use ReflectionClass;

class PWSMS {

    private static $instance = null;

    protected function __construct() {
        $this->includes();
        $this->init();
    }

    public function includes() {

        new Settings();

        new Bulk();

        new About();

        new Promotions();

        new Notice();

        new MetaBox();

        new ProductTab();

        new ProductEvents();

        new Orders();

        if ( ! class_exists( 'WP_List_Table' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
        }

        new Archive();

        new Contacts();

    }

    public function init() {
        add_action( 'widgets_init', [ $this, 'register_widget' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_style' ] );
        add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), [$this, 'action_links'] );
    }

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    public function action_links( $links ) {

        $links = array_merge( [
            '<a style="font-weight:bold;color:red;" href="' . esc_url( admin_url( '/admin.php?page=persian-woocommerce-sms-pro' ) ) . '">پیکربندی</a>',
            '<a style="font-weight:bold;color:blue;" target="_blank" href="https://hits.ir/sms-pro">پشتیبانی PRO</a>'
        ], $links );

        return $links;

    }



    public function admin_style() {
        wp_enqueue_style( 'pwsms_admin_style', PWSMS_URL . '/assets/css/admin-style.css' );
    }

    public function register_widget() {
        $widget = new Widget();
        register_widget( $widget );
    }

}
