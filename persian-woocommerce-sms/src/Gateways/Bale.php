<?php

namespace PW\PWSMS\Gateways;

/**
 * Username => $api_key
 * Bot ID   => $sender_number
 */
class Bale extends Gateway {

	protected string $api_key;
	protected int $bot_id;

	public static function name(): string {
		return 'bale.ai - پیامرسان بله';
	}

	public function send() {
		$this->bot_id  = trim( $this->senderNumber );
		$this->api_key = $this->get_token();

		if ( empty( $this->bot_id ) || empty( $this->api_key ) ) {
			return 'اطلاعات ناقص است. لطفا شناسه بات، کلید دسترسی و شماره/پیام را بررسی کنید.';
		}

		$errors = [];

		foreach ( $this->mobile as $phone ) {

			$payload = [
				'request_id'   => wp_generate_uuid4(),
				'bot_id'       => $this->bot_id,
				'phone_number' => self::normalize( $phone ),
				'message_data' => [
					'message' => [
						'text' => $this->message,
					],
				],
			];

			$response = wp_remote_post( 'https://safir.bale.ai/api/v3/send_message', [
				'method'  => 'POST',
				'headers' => [
					'api-access-key' => $this->api_key,
					'Content-Type'   => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
				'timeout' => 15,
			] );

			if ( is_wp_error( $response ) ) {
				$errors[] = 'خطا در اتصال به سرویس بله: ' . $response->get_error_message();
				continue;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $body === 'invalid_access_key' ) {
				return 'کلید API معتبر نمی‌باشد.';
			}

			if ( ! $body ) {
				$errors[] = 'پاسخ نامعتبر از سرویس بله دریافت شد.';
				continue;
			}

			if ( ! empty( $body['error_data'] ) ) {
				$errors[ $phone ] = $phone . ':<br>' . implode( '<br>', array_map( function ( $err ) {
						return ( $err['code'] ?? - 1 ) . ' => ' . ( $err['description'] ?? 'نامشخص' );
					}, $body['error_data'] ) );
			}
		}

		return ! empty( $errors ) ? implode( "\n", $errors ) : true;
	}

	private static function normalize( $phone ) {
		$phone = trim( $phone );

		if ( str_starts_with( $phone, '+98' ) ) {
			return $phone;
		}

		if ( str_starts_with( $phone, '98' ) ) {
			return '+' . $phone;
		}

		if ( str_starts_with( $phone, '0' ) && strlen( $phone ) === 11 ) {
			return '+98' . substr( $phone, 1 );
		}

		if ( strlen( $phone ) === 10 ) {
			return '+98' . $phone;
		}

		return $phone;
	}

	public function get_options(): array {
		return [
			[
				'label'       => 'توکن بله',
				'type'        => 'text',
				'description' => 'توکن را از آدرس فلان بردارید.', // @todo
			],
		];
	}
}
