<?php

namespace PW\PWSMS\Product;

use PW\PWSMS\Bot;
use PW\PWSMS\Enums\EventsEnum;
use WC_Product;

defined( 'ABSPATH' ) || exit;

class AdminEvents {

	public function low_stock( $product_id ): bool {
		$product_id = PWSMS()->product_ID( $product_id );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return false;
		}


		if ( $product->get_status() !== 'publish' ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) ) {
			return false;
		}

		if ( ! $product->is_in_stock() ) {
			return false;
		}

		if ( ! PWSMS()->is_stock_managing( $product ) ) {
			return false;
		}

		$parent_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$parent_product    = wc_get_product( $parent_product_id );

		$post_meta = '_admin_low_stock_send';
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

		$sent = $this->sms_handler( $product_id, $parent_product_id, 'low', EventsEnum::NEWSLETTER_LOW_STOCK_AUTOMATIC );

		if ( $sent ) {

			$low_stock_history[ $product_id ] = 'yes';
			$parent_product->update_meta_data( $post_meta, $low_stock_history );
			$parent_product->save();

		}

		return $sent;
	}

	public function out_of_stock( int $product_id, string $stock_status, WC_Product $product ): bool {
		$product_id = PWSMS()->product_ID( $product_id );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return false;
		}

		if ( $product->get_status() !== 'publish' ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) ) {
			return false;
		}

		$parent_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$parent_product    = wc_get_product( $parent_product_id );

		if ( ! $parent_product ) {
			return false;
		}

		$post_meta = '_admin_out_stock_send_sms';

		$out_stock_history = $parent_product->get_meta( $post_meta, true );

		if ( ! is_array( $out_stock_history ) ) {
			$out_stock_history = [];
		}

		if ( $product->is_in_stock() ) {

			if ( isset( $out_stock_history[ $product_id ] ) ) {

				unset( $out_stock_history[ $product_id ] );

				if ( empty( $out_stock_history ) ) {
					$parent_product->delete_meta_data( $post_meta );
				} else {
					$parent_product->update_meta_data( $post_meta, $out_stock_history );
				}

				$parent_product->save();

			}

			return true;
		}

		if ( isset( $out_stock_history[ $product_id ] ) && $out_stock_history[ $product_id ] === 'yes' ) {
			return false;
		}

		$sent = $this->sms_handler( $product_id, $parent_product_id, 'out', EventsEnum::MANAGERS_AUTOMATIC_OUT_OF_STOCK );

		if ( $sent ) {
			$out_stock_history[ $product_id ] = 'yes';
			$parent_product->update_meta_data( $post_meta, $out_stock_history );
			$parent_product->save();
		}

		return $sent;
	}

	private function sms_handler( $product_id, $parent_product_id, $status, $event_type ): bool {

		if ( $this->is_product_duplication() ) {
			return false;
		}

		$receivers = [];

		if ( in_array( $status, (array) PWSMS()->get_option( 'super_admin_order_status' ) ) ) {

			if ( ! $this->is_admin_updating_product() ) {
				$receivers = array_merge( $receivers, explode( ',', PWSMS()->get_option( 'super_admin_phone' ) ) );
			}

		}

		if ( ! $this->is_vendor_updating_product() ) {
			$receivers = array_merge( $receivers, array_keys( PWSMS()->product_admin_mobiles( $parent_product_id, $status ) ) );
		}

		$receivers = array_map( 'trim', $receivers );
		$receivers = array_unique( array_filter( $receivers ) );

		if ( empty( $receivers ) ) {
			return false;
		}

		$data = [
			'post_id' => $product_id,
			'type'    => $event_type,
			'mobile'  => $receivers,
			'message' => PWSMS()->replace_tags( "admin_{$status}_stock", $product_id, $parent_product_id ),
		];

		Bot::send_async( $data );

		return PWSMS()->send_sms( $data ) === true;
	}

	private function is_admin_updating_product(): bool {

		if ( ! is_admin() ) {
			return false;
		}

		$action = $_POST['action'] ?? '';

		// Direct product edition
		if ( in_array( $action, [ 'editpost', 'inline-save' ] ) ) {
			return ( isset( $_POST['post_type'] ) && $_POST['post_type'] === 'product' );
		}

		// Ajax variation saving
		if ( $action === 'woocommerce_save_variations' ) {
			return isset( $_POST['product_id'] ) && wc_get_product( absint( $_POST['product_id'] ) ) instanceof WC_Product;
		}

		return false;
	}

	private function is_vendor_updating_product(): bool {

		if ( ! function_exists( 'dokan_is_user_seller' ) ) {
			return false;
		}

		if ( ! is_user_logged_in() || ! dokan_is_user_seller( get_current_user_id() ) ) {
			return false;
		}

		// Legacy simple product update.
		if ( isset( $_POST['dokan_update_product'], $_POST['dokan_product_id'] ) ) {
			return true;
		}

		// Legacy ajax variation update
		$action = sanitize_key( wp_unslash( $_POST['action'] ?? '' ) );

		if ( $action === 'dokan_save_variations' ) {
			return isset( $_POST['product_id'] ) && wc_get_product( absint( $_POST['product_id'] ) ) instanceof WC_Product;
		}

		// Modern Rest API (soft detection)
		if ( wp_is_json_request() ) {

			$route = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
			$route = explode( '/', $route );

			if ( in_array( 'dokan', $route ) && in_array( 'products', $route ) ) {
				return true;
			}

		}

		return false;
	}

	private function is_product_duplication(): bool {

		return is_admin() && ( $_GET['action'] ?? '' ) === 'duplicate_product';
	}
}
