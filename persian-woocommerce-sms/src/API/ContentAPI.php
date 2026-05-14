<?php

namespace PW\PWSMS\API;

use PW\PWSMS\API\RestAPI;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class ContentAPI extends RestAPI {

	public function register_routes() {

		register_rest_route( 'pwsms/content', 'group-message/(?P<product_id>\d+)/(?P<group>[a-zA-Z0-9_-]+)', [
			[
				'methods'             => [ 'GET', 'POST' ],
				'callback'            => [ $this, 'group_message_text' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
		] );

	}

	public function permission_callback( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}

		return current_user_can( 'manage_options' );
	}

	public function group_message_text( WP_REST_Request $request ) {
		$product_id = $request->get_param( 'product_id' );
		$group      = $request->get_param( 'group' );

		$product = wc_get_product( $product_id );

		if ( ! PWSMS()->is_wc_product( $product ) ) {
			return false;
		}

		$parent_product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$parent_product    = wc_get_product( $parent_product_id );

		$group_options_map = [
			'_onsale' => 'notif_onsale_sms',
			'_in'     => 'notif_no_stock_sms',
			'_low'    => 'notif_low_stock_sms'
		];

		$key = $group_options_map[ $group ];

		if ( ! PWSMS()->is_wc_product( $parent_product ) ) {
			return false;
		}

		$message = PWSMS()->replace_tags( $key, $product_id, $parent_product_id );

		return new WP_REST_Response( [
			'message' => $message,
		], 200 );
	}

}