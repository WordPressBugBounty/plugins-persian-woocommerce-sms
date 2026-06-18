<?php

namespace PW\PWSMS\Product;

use PW\PWSMS\Enums\EventsEnum;
use PW\PWSMS\Subscription\Contacts;
use PW\PWSMS\Helper;
use WC_Product;

defined( 'ABSPATH' ) || exit;

class CustomerEvents {

	public static array $groups_to_remove = [];

	public function onsale( int $product_id ): bool {
		$product = wc_get_product( $product_id );

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) && ! $product->is_type( 'variation' ) ) {
			return false;
		}

		if ( ! $product->is_in_stock() && ! $product->is_purchasable() ) {
			return false;
		}

		$parent_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$parent_product    = wc_get_product( $parent_product_id );

		if ( ! PWSMS()->is_wc_product( $parent_product ) ) {
			return false;
		}

		if ( ! PWSMS()->has_notif_condition( 'enable_onsale', $parent_product_id ) ) {
			return false;
		}

		$group       = '_onsale';
		$post_meta   = '_onsale_send';
		$schedule    = 'woocommerce_sms_send_onsale_event';
		$sale_price  = $product->get_sale_price();
		$is_schedule = doing_action( $schedule );

		if ( ! $product->is_on_sale() ) {

			if ( ! $is_schedule ) {

				$date_from = PWSMS()->product_sale_price_time( $product_id, 'from' );

				if ( ! empty( $date_from ) && $date_from > time() ) {
					wp_clear_scheduled_hook( $schedule, [ $product_id ] );
					wp_schedule_single_event( $date_from + 3600, $schedule, [ $product_id ] );
				}

				$sent_history = $parent_product->get_meta( $post_meta, true );
				if ( is_array( $sent_history ) && isset( $sent_history[ $product_id ] ) ) {
					unset( $sent_history[ $product_id ] );

					if ( empty( $sent_history ) ) {
						$parent_product->delete_meta_data( $post_meta );
					} else {
						$parent_product->update_meta_data( $post_meta, $sent_history );
					}

					$parent_product->save();
				}

			}

			return false;
		}

		$sent_history = $parent_product->get_meta( $post_meta, true );

		if ( ! is_array( $sent_history ) ) {
			$sent_history = [];
		}

		if ( isset( $sent_history[ $product_id ] ) && $sent_history[ $product_id ] === $sale_price ) {
			return false;
		}

		wp_clear_scheduled_hook( $schedule, [ $product_id ] );

		$receivers = Contacts::get_contacts_mobile( $parent_product_id, $group );
		$receivers = array_merge( $receivers, Contacts::get_contacts_mobile( $product_id, $group ) );
		$receivers = Helper::remove_sent_mobiles( $receivers, EventsEnum::NEWSLETTER_SALE_AUTOMATIC, $product_id );

		if ( empty( $receivers ) ) {
			return false;
		}

		$data = [
			'post_id' => $product_id,
			'type'    => EventsEnum::NEWSLETTER_SALE_AUTOMATIC,
			'mobile'  => $receivers,
			'message' => PWSMS()->replace_tags( 'notif_onsale_sms', $product_id, $parent_product_id ),
		];

		$message_sent = PWSMS()->send_sms( $data );

		if ( ! $message_sent ) {
			return false;
		}

		$remove_group = PWSMS()->get_option( 'notif_onsale_remove_contacts' );

		if ( $remove_group ) {
			self::$groups_to_remove[ $product_id ][ $group ] = $receivers;
		}

		$sent_history[ $product_id ] = $sale_price;
		$parent_product->update_meta_data( $post_meta, $sent_history );
		$parent_product->save();

		return true;
	}

	public function in_stock( int $product_id, string $stock_status, WC_Product $product ): bool {
		$parent_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$parent_product    = wc_get_product( $parent_product_id );

		if ( ! PWSMS()->is_wc_product( $parent_product ) ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) && ! $product->is_type( 'variation' ) ) {
			return false;
		}

		$group            = '_in';
		$post_meta        = '_in_stock_send';
		$in_stock_history = $parent_product->get_meta( $post_meta, true );

		if ( ! is_array( $in_stock_history ) ) {
			$in_stock_history = [];
		}

		if ( ! $product->is_in_stock() ) {

			if ( ! isset( $in_stock_history[ $product_id ] ) ) {
				return true;
			}

			unset( $in_stock_history[ $product_id ] );

			if ( empty( $in_stock_history ) ) {
				$parent_product->delete_meta_data( $post_meta );
			} else {
				$parent_product->update_meta_data( $post_meta, $in_stock_history );
			}

			$parent_product->save();

			return true;
		}

		if ( isset( $in_stock_history[ $product_id ] ) && $in_stock_history[ $product_id ] === 'yes' ) {
			return false;
		}

		if ( ! PWSMS()->has_notif_condition( 'enable_notif_no_stock', $parent_product_id ) ) {
			return false;
		}

		$receivers = Contacts::get_contacts_mobile( $parent_product_id, $group );
		$receivers = array_merge( $receivers, Contacts::get_contacts_mobile( $product_id, $group ) );
		$receivers = Helper::remove_sent_mobiles( $receivers, EventsEnum::NEWSLETTER_IN_STOCK_AUTOMATIC, $product_id );

		if ( empty( $receivers ) ) {
			return false;
		}

		$message = PWSMS()->replace_tags( 'notif_no_stock_sms', $product_id, $parent_product_id );

		$data = [
			'post_id' => $product_id,
			'type'    => EventsEnum::NEWSLETTER_IN_STOCK_AUTOMATIC,
			'mobile'  => $receivers,
			'message' => $message,
		];

		$message_sent = PWSMS()->send_sms( $data );

		if ( ! $message_sent ) {
			return false;
		}

		$remove_group = PWSMS()->get_option( 'notif_no_stock_remove_contacts' );

		if ( $remove_group ) {
			self::$groups_to_remove[ $product_id ][ $group ] = $receivers;
		}

		$in_stock_history[ $product_id ] = 'yes';
		$parent_product->update_meta_data( $post_meta, $in_stock_history );
		$parent_product->save();

		return true;
	}

	public function low_stock( $product_id ): bool {
		if ( is_object( $product_id ) && method_exists( $product_id, 'get_id' ) ) {
			$product_id = $product_id->get_id();
		} elseif ( is_object( $product_id ) && isset( $product_id->ID ) ) {
			$product_id = $product_id->ID;
		}

		$product_id = intval( $product_id );

		if ( 'yes' !== get_option( 'woocommerce_manage_stock' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) && ! $product->is_type( 'variation' ) ) {
			return false;
		}

		$parent_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$parent_product    = wc_get_product( $parent_product_id );

		if ( ! PWSMS()->is_wc_product( $parent_product ) ) {
			return false;
		}

		if ( ! PWSMS()->is_stock_managing( $product ) ) {
			return false;
		}

		$group     = '_low';
		$post_meta = '_low_stock_send';
		$quantity  = PWSMS()->product_stock_qty( $product );

		$low_stock_history = $parent_product->get_meta( $post_meta, true );

		if ( ! is_array( $low_stock_history ) ) {
			$low_stock_history = [];
		}

		$low_stock_limit = $product->get_low_stock_amount() ?: get_option( 'woocommerce_notify_low_stock_amount' );

		if ( $quantity > $low_stock_limit ) {

			if ( ! isset( $low_stock_history[ $product_id ] ) ) {
				return true;
			}

			unset( $low_stock_history[ $product_id ] );

			if ( empty( $low_stock_history ) ) {
				$parent_product->delete_meta_data( $post_meta );
			} else {
				$parent_product->update_meta_data( $post_meta, $low_stock_history );
			}

			$parent_product->save();

			return true;
		}

		if ( isset( $low_stock_history[ $product_id ] ) && $low_stock_history[ $product_id ] === 'yes' ) {
			return false;
		}

		if ( ! PWSMS()->has_notif_condition( 'enable_notif_low_stock', $parent_product_id ) ) {
			return false;
		}

		$message   = PWSMS()->replace_tags( 'notif_low_stock_sms', $product_id, $parent_product_id );
		$receivers = Contacts::get_contacts_mobile( $parent_product_id, $group );
		$receivers = array_merge( $receivers, Contacts::get_contacts_mobile( $product_id, $group ) );
		$receivers = Helper::remove_sent_mobiles( $receivers, EventsEnum::NEWSLETTER_LOW_STOCK_AUTOMATIC, $parent_product_id );

		if ( empty( $receivers ) ) {
			return false;
		}

		$data = [
			'post_id' => $product_id,
			'type'    => EventsEnum::NEWSLETTER_LOW_STOCK_AUTOMATIC,
			'mobile'  => $receivers,
			'message' => $message,
		];

		\PW\PWSMS\Bot::send_async( $data );

		$message_sent = PWSMS()->send_sms( $data );

		$remove_group = PWSMS()->get_option( 'notif_low_stock_remove_contacts' );

		if ( $message_sent && $remove_group ) {
			self::$groups_to_remove[ $product_id ][ $group ] = $receivers;
		}

		if ( $message_sent ) {
			$low_stock_history[ $product_id ] = 'yes';
			$parent_product->update_meta_data( $post_meta, $low_stock_history );
			$parent_product->save();
		}

		return true;
	}

	public function bulk_remove_contacts_groups(): void {
		if ( empty( self::$groups_to_remove ) ) {
			return;
		}

		foreach ( self::$groups_to_remove as $product_id => $groups ) {

			if ( ! $product = wc_get_product( $product_id ) ) {
				continue;
			}

			foreach ( $groups as $group_name => $receivers ) {

				if ( $this->should_keep_contact_group( $product, $group_name ) ) {
					continue;
				}

				Helper::remove_contacts_group( $receivers, $group_name, $product_id );

				$product = wc_get_product( $product_id );

				if ( $product && $product->get_parent_id() ) {
					Helper::remove_contacts_group( $receivers, $group_name, $product->get_parent_id() );
				}

			}

		}

	}

	private function should_keep_contact_group( WC_Product $parent_product, string $group_name ): bool {

		$meta_keys = [
			'_onsale' => '_onsale_send',
			'_in'     => '_in_stock_send',
			'_low'    => '_low_stock_send',
		];

		if ( ! isset( $meta_keys[ $group_name ] ) ) {
			return false;
		}

		$fresh_history = $parent_product->get_meta( $meta_keys[ $group_name ], true );
		$fresh_history = is_array( $fresh_history ) ? $fresh_history : [];

		$low_stock_limit = get_option( 'woocommerce_notify_low_stock_amount' );
		$no_stock_limit  = get_option( 'woocommerce_notify_no_stock_amount' );

		foreach ( $parent_product->get_children() as $child_id ) {

			$child = wc_get_product( $child_id );

			if ( ! $child ) {
				continue;
			}

			if ( isset( $fresh_history[ $child_id ] ) ) {
				continue;
			}

			if ( $group_name === '_onsale' && $child->is_on_sale() ) {
				return true;
			}

			if ( $group_name === '_in' && $child->is_in_stock() ) {
				return true;
			}

			if ( $group_name === '_low' ) {

				$qty = PWSMS()->product_stock_qty( $child );

				if ( $qty <= $low_stock_limit && $qty > $no_stock_limit ) {
					return true;
				}

			}

		}

		return false;
	}
}
