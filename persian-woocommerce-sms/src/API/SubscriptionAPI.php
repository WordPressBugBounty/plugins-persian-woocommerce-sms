<?php

namespace PW\PWSMS\API;

use Exception;
use PW\PWSMS\Settings\Settings;
use PW\PWSMS\Subscription\Contacts;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class SubscriptionAPI extends RestAPI {

	public function register_routes() {
		register_rest_route( 'pwsms/notification', 'subscribe', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'subscribe' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'product_id' => [
					'required'          => true,
					'validate_callback' => [ $this, 'validate_product_id' ],
				],
				'sms_group'  => [
					'required' => true,
				],
				'sms_mobile' => [
					'required' => true,
				]
			],
		] );

		register_rest_route( 'pwsms/notification', 'groups', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'groups' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'product_id' => [
					'required'          => true,
					'validate_callback' => [ $this, 'validate_product_id' ],
				],
			],
		] );
	}

	public function subscribe( WP_REST_Request $request ): WP_REST_Response {
		$product_id = intval( $request->get_param( 'product_id' ) );

		if ( empty( $product_id ) ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'شناسه محصول یافت نشد.' ], 400 );
		}

		$can_be_subscribe = ! PWSMS()->has_notif_condition( 'notif_only_loggedin', $product_id ) || is_user_logged_in();

		if ( ! $can_be_subscribe ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => esc_attr( PWSMS()->get_sms_setting( 'notif_only_loggedin_text', $product_id ) )
			], 403 );
		}

		$mobile = PWSMS()->modify_mobile( sanitize_text_field( $request->get_param( 'sms_mobile' ) ?? '' ) );
		if ( empty( $mobile ) ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'شماره موبایل را وارد نمایید.' ], 200 );
		}

		if ( ! PWSMS()->validate_mobile( $mobile ) ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'شماره موبایل معتبر نیست.' ], 200 );
		}

		$sms_groups = $request->get_param( 'sms_group' );
		if ( empty( $sms_groups ) ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'انتخاب یکی از گزینه ها الزامیست.' ], 200 );
		}

		$groups  = ( new Settings() )->sanitize_array_text_fields( (array) $sms_groups );
		$contact = (array) Contacts::get_contact_by_mobile( $product_id, $mobile );

		if ( ! empty( $contact['id'] ) ) {

			$old_groups = ! empty( $contact['groups'] ) ? explode( ',', $contact['groups'] ) : [];
			$new_groups = array_merge( $old_groups, $groups );

			$update = Contacts::update_contact( [
				'id'         => $contact['id'],
				'product_id' => $product_id,
				'mobile'     => $mobile,
				'groups'     => $new_groups,
			] );

			if ( $update !== false ) {
				return new WP_REST_Response( [ 'success' => true, 'message' => 'اطلاعات شما با موفقیت بروز شد.' ], 200 );
			}

		} else {

			$insert = Contacts::insert_contact( [
				'product_id' => $product_id,
				'mobile'     => $mobile,
				'groups'     => $groups,
			] );

			if ( $insert ) {
				return new WP_REST_Response( [ 'success' => true, 'message' => 'اطلاعات شما با موفقیت ثبت شد.' ], 200 );
			}

		}

		return new WP_REST_Response( [ 'success' => false, 'message' => 'خطایی رخ داده است. مجددا تلاش کنید.' ], 500 );
	}

	public function groups( WP_REST_Request $request ) {
		$product_id = intval( $request->get_param( 'product_id' ) );

		if ( ! $product_id ) {
			return new WP_Error( 'invalid_product', 'Product ID is missing or invalid', [ 'status' => 400 ] );
		}

		$groups = Contacts::get_groups( $product_id, true, true );

		if ( empty( $groups ) ) {
			return new WP_REST_Response( [], 200 );
		}

		$formatted_groups = [];
		foreach ( $groups as $code => $text ) {
			$formatted_groups[] = [
				'code' => $code,
				'text' => $text
			];
		}

		return new WP_REST_Response( $formatted_groups, 200 );
	}

	public function validate_product_id( $product_id ): bool {
		return is_numeric( trim( $product_id ) );
	}

	public function permission_callback( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}

		return true;
	}
}