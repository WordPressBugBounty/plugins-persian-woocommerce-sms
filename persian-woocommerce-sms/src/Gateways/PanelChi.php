<?php

namespace PW\PWSMS\Gateways;

class PanelChi extends Gateway {

	public string $api_url = 'https://api.panelchi.com/sms';
	public string $api_key;
	public array $failed_numbers = [];

	public static function id(): string {
		return 'panelchi';
	}

	public static function name(): string {
		return 'PanelChi.com - پنل چی';
	}

	public function send() {
		$this->api_key = $this->get_token();

		if ( empty( $this->api_key ) ) {
			return 'کلید API را در بخش تنظیمات وب‌سرویس تعریف کنید.';
		}

		if ( ! str_starts_with( $this->api_key, 'Bearer' ) ) {
			$this->api_key = 'Bearer ' . $this->api_key;
		}

		if ( $this->is_pattern() ) {
			return $this->send_pattern_sms();
		}

		return $this->send_normal_sms();
	}

	private function send_pattern_sms() {

		$pattern = $this->parse_pattern();

		$headers = [
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => $this->api_key,
		];

		foreach ( $this->mobile as $recipient ) {

			$payload = [
				'sourceNumber' => $this->senderNumber,
				'recipient'    => $recipient,
				'pattern'      => $pattern['code'],
				'variables'    => $pattern['vars'],
			];

			$remote = wp_remote_post( $this->api_url . '/pattern', [
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			] );

			if ( is_wp_error( $remote ) ) {
				$this->failed_numbers[ $recipient ] = $remote->get_error_message();
				continue;
			}

			$response_message = wp_remote_retrieve_response_message( $remote );
			$response_code    = wp_remote_retrieve_response_code( $remote );

			if ( empty( $response_code ) ) {
				$this->failed_numbers[ $recipient ] = $response_code . ' -> ' . $response_message;
				continue;
			}

			$response = wp_remote_retrieve_body( $remote );

			if ( empty( $response ) ) {
				$this->failed_numbers[ $recipient ] = 'پاسخی از وب‌سرویس دریافت نشد.';
				continue;
			}

			$response_data = json_decode( $response, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$this->failed_numbers[ $recipient ] = 'قالب پاسخ دریافتی از وب‌سرویس نامعتبر است.';
				continue;
			}

			if ( ! isset( $response_data['data']['uid'] ) ) {
				$this->failed_numbers[ $recipient ] = 'شناسه پیامک ارسالی، از سمت وبسرویس، یافت نشد.';
				continue;
			}

			$this->failed_numbers[ $recipient ] = 'خطای وب‌سرویس: ' . ( $response_data['message'] ?? $response_data['error'] ?? 'خطایی ناشناخته رخ داده است.' );
		}

		return $this->format_failed_numbers();
	}

	private function send_normal_sms() {

		$payload = [
			'sourceNumber' => $this->senderNumber,
			'recipients'   => $this->mobile,
			'message'      => $this->message,
		];

		$headers = [
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => $this->api_key,
		];

		$remote = wp_remote_post( $this->api_url . '/send', [
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
		] );

		if ( is_wp_error( $remote ) ) {
			return $remote->get_error_message();
		}

		$response_message = wp_remote_retrieve_response_message( $remote );
		$response_code    = wp_remote_retrieve_response_code( $remote );

		if ( empty( $response_code ) ) {
			return $response_code . ' -> ' . $response_message;
		}

		$response = wp_remote_retrieve_body( $remote );

		if ( empty( $response ) ) {
			return 'پاسخی از وب‌سرویس دریافت نشد.';
		}

		$response_data = json_decode( $response, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return 'قالب پاسخ دریافتی از وب‌سرویس نامعتبر است.';
		}

		if ( isset( $response_data['data']['uid'] ) ) {
			return true;
		}

		return 'خطای وب‌سرویس: ' . ( $response_data['message'] ?? $response_data['error'] ?? 'خطایی ناشناخته رخ داده است.' );
	}

	private function format_failed_numbers() {

		if ( empty( $this->failed_numbers ) ) {
			return true;
		}

		$grouped = [];

		foreach ( $this->failed_numbers as $number => $message ) {
			if ( ! isset( $grouped[ $message ] ) ) {
				$grouped[ $message ] = [];
			}
			$grouped[ $message ][] = $number;
		}

		return implode( ', ', array_map( function ( string $message, array $numbers ) {
			return implode( ',', $numbers ) . ': ' . $message;
		}, array_keys( $grouped ), $grouped ) );
	}
}
