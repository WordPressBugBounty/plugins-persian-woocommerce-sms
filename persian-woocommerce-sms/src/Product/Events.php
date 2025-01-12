<?php

namespace PW\PWSMS\Product;

use PW\PWSMS\Repositories\ContactsRepository;
use PW\PWSMS\Repositories\SMSRepository;
use PW\PWSMS\Subscribe\Contacts;

defined( 'ABSPATH' ) || exit;

class Events {

	private $enable_notification = false;
	private $enable_super_admin_sms = false;
	private $enable_product_admin_sms = false;

	public function __construct() {

		$this->enable_notification      = PWSMS()->get_option( 'enable_notif_sms_main' );
		$this->enable_super_admin_sms   = PWSMS()->get_option( 'enable_super_admin_sms' );
		$this->enable_product_admin_sms = PWSMS()->get_option( 'enable_product_admin_sms' );

		if ( $this->enable_notification || $this->enable_super_admin_sms || $this->enable_product_admin_sms ) {
			add_action( 'init', [ $this, 'init' ] );
		}
	}

	public function init() {

		$action = ! empty( $_POST['action'] ) ? str_ireplace( 'woocommerce_', '', sanitize_text_field( $_POST['action'] ) ) : '';
		if ( in_array( $action, [ 'add_variation', 'link_all_variations' ] ) ) {
			return;
		}

		/*onSale*/
		add_action( 'woocommerce_process_product_meta', [ $this, 'on_sale_sms' ], 9999, 1 );
		add_action( 'woocommerce_update_product_variation', [ $this, 'on_sale_sms' ], 9999, 1 );
		add_action( 'woocommerce_sms_send_onsale_event', [ $this, 'on_sale_sms' ] );
		/*inStock*/
		add_action( 'woocommerce_product_set_stock_status', [ $this, 'in_stock_sms' ] );
		add_action( 'woocommerce_variation_set_stock_status', [ $this, 'in_stock_sms' ] );
		/*outStock*/
		add_action( 'woocommerce_product_set_stock_status', [ $this, 'unavailable_sms' ] );
		add_action( 'woocommerce_variation_set_stock_status', [ $this, 'unavailable_sms' ] );
		/*lowStock*/
		add_action( 'woocommerce_low_stock', [ $this, 'running_out_stock_sms' ] );
		add_action( 'woocommerce_product_set_stock', [ $this, 'running_out_stock_sms' ] );
		add_action( 'woocommerce_variation_set_stock', [ $this, 'running_out_stock_sms' ] );
	}

	// وقتی محصول فروش ویژه شد : کاربر
	public function on_sale_sms( int $product_id ) {

		$product = wc_get_product( $product_id );
		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return false;
		}
		$parent_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$parent_product    = wc_get_product( $parent_product_id );
		if ( ! PWSMS()->is_wc_product( $parent_product ) ) {
			return false;
		}

		/*-----------------------------------------------------------------*/

		$post_meta   = '_onsale_send';
		$schedule    = 'woocommerce_sms_send_onsale_event';
		$sale_price  = $product->get_sale_price();
		$is_schedule = current_action() == $schedule;

		if ( $sale_price === $parent_product->get_meta( $post_meta, true ) ) {
			return false;
		} elseif ( ! $is_schedule ) {
			$parent_product->delete_meta_data( $post_meta );
			$parent_product->save();
		}

		if ( PWSMS()->has_notif_condition( 'enable_onsale', $parent_product_id ) ) {

			if ( ! $product->is_on_sale() ) {

				if ( ! $is_schedule ) {
					$date_from = PWSMS()->product_sale_price_time( $product_id, 'from' );
					if ( ! empty( $date_from ) && $date_from > strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
						wp_schedule_single_event( $date_from + 3600, $schedule, [ $product_id ] );
					}
				}
				$parent_product->delete_meta_data( $post_meta );
				$parent_product->save();

				return true;
			}

			wp_clear_scheduled_hook( $schedule );

			$data = [
				'post_id' => $parent_product_id,
				'type'    => 9,
				'mobile'  => Contacts::get_contacts_mobile( $parent_product_id, '_onsale' ),
				'message' => PWSMS()->replace_tags( 'notif_onsale_sms', $product_id, $parent_product_id ),
			];

			PWSMS()->send_sms( $data );
			$parent_product->update_meta_data( $post_meta, $sale_price );
			$parent_product->save();

			return true;
		}

		return false;
	}

	// وقتی محصول موجود شد : کاربر
	public function in_stock_sms( $product_id ): bool {
		$type    = 11;
		$product = wc_get_product( $product_id );
		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return false;
		}
		$parent_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$parent_product    = wc_get_product( $product_id );
		if ( ! PWSMS()->is_wc_product( $parent_product ) ) {
			return false;
		}
		/*-----------------------------------------------------------------*/

		$post_meta = '_in_stock_send';

		if ( ! $product->is_in_stock() ) {
			$parent_product->delete_meta_data( $post_meta );
			$parent_product->save();

			return true;
		}

		$in_stock_send = $product->get_meta( $post_meta, true );

		if ( PWSMS()->maybe_bool( $in_stock_send ) ) {
			return false;
		}

		if ( ! PWSMS()->has_notif_condition( 'enable_notif_no_stock', $parent_product_id ) ) {
			return false;
		}

		$receivers = Contacts::get_contacts_mobile( $parent_product_id, '_in' );
		// Remove users who already got the subscription message.
		$receivers = SMSRepository::instance()->remove_sent_mobiles( $receivers, $type, $parent_product_id );

		if ( empty( $receivers ) ) {
			return false;
		}

		$message = PWSMS()->replace_tags( 'notif_no_stock_sms', $product_id, $parent_product_id );

		$data = [
			'post_id' => $parent_product_id,
			'type'    => $type,
			'mobile'  => $receivers,
			'message' => $message,
		];


		$message_sent = PWSMS()->send_sms( $data );

		$remove_group = PWSMS()->get_option( 'notif_no_stock_remove_contacts' );

		if ( true == $message_sent && $remove_group ) {
			// Remove _in group from the contact groups which already got the sms!
			ContactsRepository::instance()->remove_contacts_group( $receivers, '_in', $parent_product_id );
		}

		$parent_product->update_meta_data( $post_meta, 'yes' );
		$parent_product->save();

		return true;

	}

	// وقتی محصول ناموجود شد : مدیران کل و مدیران محصول
	public function unavailable_sms( $product_id ) {

		$product = wc_get_product( $product_id );

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return false;
		}

		$parent_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$parent_product    = wc_get_product( $parent_product_id );

		if ( ! PWSMS()->is_wc_product( $parent_product ) ) {
			return false;
		}
		/*-----------------------------------------------------------------*/

		$post_meta = '_out_stock_send_sms';

		if ( $product->is_in_stock() ) {
			$parent_product->delete_meta_data( $post_meta );
			$parent_product->save();
			return true;
		}

		$out_stock_send = $parent_product->get_meta( $post_meta, true );
		if ( PWSMS()->maybe_bool( $out_stock_send ) ) {
			return false;
		}

		$this->admins_stock_sms( $product_id, $parent_product_id, 'out', 7 );

		$parent_product->update_meta_data( $post_meta, 'yes' );
		$parent_product->save();

		return true;
	}

	// محصول رو به اتمام است : مدیر و کاربر

	private function admins_stock_sms( $product_id, $parent_product_id, $status, $type ) {

		$mobiles = [];
		if ( $this->enable_super_admin_sms ) {
			if ( in_array( $status, (array) PWSMS()->get_option( 'super_admin_order_status' ) ) ) {
				$mobiles = array_merge( $mobiles, explode( ',', PWSMS()->get_option( 'super_admin_phone' ) ) );
			}
		}
		if ( $this->enable_product_admin_sms ) {
			$mobiles = array_merge( $mobiles, array_keys( PWSMS()->product_admin_mobiles( $parent_product_id, $status ) ) );
		}

		$mobiles = array_map( 'trim', $mobiles );
		$mobiles = array_unique( array_filter( $mobiles ) );

		if ( ! empty( $mobiles ) ) {
			$data = [
				'post_id' => $parent_product_id,
				'type'    => $type,
				'mobile'  => $mobiles,
				'message' => PWSMS()->replace_tags( "admin_{$status}_stock", $product_id, $parent_product_id ),
			];

			return PWSMS()->send_sms( $data ) === true;
		}

		return false;
	}

	public function running_out_stock_sms( $product_id ) {

		if ( 'yes' !== get_option( 'woocommerce_manage_stock' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return false;
		}

		$parent_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$parent_product    = wc_get_product( $parent_product_id );

		if ( ! PWSMS()->is_wc_product( $parent_product ) ) {
			return false;
		}
		/*-----------------------------------------------------------------*/

		if ( ! PWSMS()->is_stock_managing( $product ) ) {
			return false;
		}

		$post_meta = '_low_stock_send';

		$quantity = PWSMS()->product_stock_qty( $product );
		if ( $quantity > get_option( 'woocommerce_notify_low_stock_amount' ) || $quantity <= get_option( 'woocommerce_notify_no_stock_amount' ) ) {
			$parent_product->delete_meta_data( $post_meta );
			$parent_product->save();

			return true;

		}
		$low_stock_send = $parent_product->get_meta( $post_meta );
		if ( PWSMS()->maybe_bool( $low_stock_send ) ) {
			return false;
		}

		//کاربر
		if ( PWSMS()->has_notif_condition( 'enable_notif_low_stock', $parent_product_id ) ) {
			$type      = 13;
			$message   = PWSMS()->replace_tags( 'notif_low_stock_sms', $product_id, $parent_product_id );
			$receivers = Contacts::get_contacts_mobile( $parent_product_id, '_low' );
			// Remove users who already got the subscription message.
			$receivers = SMSRepository::instance()->remove_sent_mobiles( $receivers, $type, $parent_product_id );

			if ( empty( $receivers ) ) {
				return false;
			}

			$data = [
				'post_id' => $parent_product_id,
				'type'    => $type,
				'mobile'  => $receivers,
				'message' => $message,
			];

			PWSMS()->send_sms( $data );
		}

		//مدیر
		$this->admins_stock_sms( $product_id, $parent_product_id, 'low', 8 );
		$parent_product->update_meta_data( $post_meta, 'yes' );
		$parent_product->save();

		return true;

	}
}

