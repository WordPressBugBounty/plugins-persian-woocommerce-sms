<?php

namespace PW\PWSMS\API;

use PW\PWSMS\API\RestAPI;
use PW\PWSMS\Enums\EventsEnum;
use PW\PWSMS\Helper;
use PW\PWSMS\PWSMS;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class SMSAPI extends RestAPI {

	public function register_routes() {
		register_rest_route( 'pwsms/sms', 'send', [
			[
				'methods'             => [ 'GET', 'POST' ],
				'callback'            => [ $this, 'send' ],
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

	public function send( $request ): WP_REST_Response {
		$message = $request->get_param( 'message' );
		$mobile  = $request->get_param( 'mobile' );

		$raw_mobile_string = sanitize_text_field( $mobile );
		$mobile_array      = array_filter( array_map( 'trim', explode( ',', $raw_mobile_string ) ) );
		$mobile_array      = array_unique( $mobile_array );
		$mobile_array      = array_filter( $mobile_array, [ PWSMS(), 'validate_mobile' ] );

		$data              = [
			'type'    => EventsEnum::BULK_SEND,
			'mobile'  => $mobile_array,
			'message' => ! empty( $message ) ? sanitize_textarea_field( $message ) : '',
		];

		$response = PWSMS()->send_sms( $data );

		if ( $response === true ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => 'پیامک با موفقیت ارسال شد.',
				'count'   => count( $mobile_array ),
				'details' => 'تعداد مخاطبین با حذف شماره‌های تکراری: ' . count( $mobile_array ) . ' شماره',
			], 200 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => 'پیامک ارسال نشد',
			'error'   => is_string( $response ) ? $response : 'خطای ناشناخته',
		], 503 );
	}
}