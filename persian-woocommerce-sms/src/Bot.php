<?php

namespace PW\PWSMS;

use Exception;
use PW\PWSMS\Services\ScheduleService;

class Bot {

	private static function get_uuids(): array {

		$raw_uuids = trim( PWSMS()->get_option( 'super_admin_bots' ) );

		if ( empty( $raw_uuids ) ) {
			return [];
		}

		$clean = str_replace( [ "\n", "\r", ",", ";" ], '|', $raw_uuids );

		$parts = explode( '|', $clean );

		return array_values( array_filter( array_map( 'trim', $parts ) ) );
	}

	public static function send_async( array $data ): bool {
		$uuids = self::get_uuids();

		if ( empty( $uuids ) ) {
			return false;
		}

		return ScheduleService::dispatch( [ self::class, 'send' ], $data, 'pwsms_bot' );
	}

	/**
	 * @throws Exception
	 */
	public static function send( array $data ): bool {
		$uuids = self::get_uuids();

		if ( empty( $uuids ) ) {
			return false;
		}

		if ( empty( $data['message'] ) ) {
			throw new Exception( '[PWSMS][Bot] Message cannot be empty.' );
		}

		$message = $data['message'];
		$buttons = [];

		if ( ! empty( $data['post_id'] ) ) {

			$order_id  = absint( $data['post_id'] );
			$order     = wc_get_order( $order_id );
			$edit_link = $order ? $order->get_edit_order_url() : '';

			$buttons = [
				[
					[
						'text' => '🔍 مشاهده سفارش ' . $order_id,
						'url'  => $edit_link,
					],
				],
			];
		}

		$payload = [
			'text'       => $message,
			'recipients' => $uuids,
		];

		if ( $buttons ) {
			$payload['buttons'] = $buttons;
		}

		$response = wp_remote_post(
			'https://bot.woocommerce.ir/api/v1/sendMessage',
			[
				'headers' => [
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( '[PWSMS][Bot] ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code === 200 ) {
			return true;
		}

		throw new Exception( '[PWSMS][Bot] API error (' . $code . '): ' . $body );
	}
}
