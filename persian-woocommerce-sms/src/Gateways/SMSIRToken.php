<?php

namespace PW\PWSMS\Gateways;


class SMSIRToken extends Gateway {

	public string $api_url = 'https://api.sms.ir/v1/';
	public string $api_key;
	public array $failed_numbers;

	public static function id(): string {
		return 'smsir-new';
	}

	public static function name(): string {
		return 'SMS.ir (کلید دسترسی)';
	}

	public function send() {
		$this->api_key = $this->get_token();

		if ( empty( $this->api_key ) ) {
			return 'کلید وبسرویس را در بخش تنظیمات وبسرویس تعریف کنید.';
		}

		if ( $this->is_pattern() ) {
			return $this->send_pattern_sms();
		}

		return $this->send_normal_sms();
	}

	private function send_pattern_sms() {

		$pattern = $this->parse_pattern();

		$parameters = [];

		foreach ( $pattern['vars'] as $name => $value ) {
			$parameters[] = [
				"name"  => $name,
				"value" => $value,
			];
		}

		$headers = [
			'Content-Type' => 'application/json',
			'x-api-key'    => $this->api_key,
		];

		$payload = [
			"templateId" => $pattern['code'],
			"parameters" => $parameters,
		];

		foreach ( $this->mobile as $recipient ) {

			$payload["mobile"] = $recipient;

			$remote = wp_remote_post( $this->api_url . "send/verify", [
				'method'      => 'POST',
				'body'        => json_encode( $payload ),
				'headers'     => $headers,
				'timeout'     => 5,
				'data_format' => 'body',
			] );

			if ( is_wp_error( $remote ) ) {
				$this->failed_numbers[ $recipient ] = $remote->get_error_message();
			}

			$response_message = wp_remote_retrieve_response_message( $remote );
			$response_code    = wp_remote_retrieve_response_code( $remote );

			if ( empty( $response_code ) || 200 != $response_code ) {
				$this->failed_numbers[ $recipient ] = $response_code . ' -> ' . $response_message;
				continue;
			}

			$response_body = wp_remote_retrieve_body( $remote );

			if ( empty( $response_body ) ) {
				$this->failed_numbers[ $recipient ] = 'پاسخی از وب‌سرویس دریافت نشد.';
				continue;
			}

			$response_data = json_decode( $response_body, true );

			if ( ! empty( json_last_error() ) ) {
				$this->failed_numbers[ $recipient ] = 'قالب پاسخ دریافتی از وب‌سرویس نامعتبر است.';
				continue;
			}

			if ( ! isset( $response_data['status'] ) && $response_data['status'] = ! '1' ) {
				$error_message                      = $response_data['message'] ?? 'خطایی ناشناخته رخ داده است.';
				$this->failed_numbers[ $recipient ] = $error_message;
				continue;
			}

			if ( isset( $response_data['status'] ) && $response_data['status'] == '1' ) {
				continue;
			}

		}

		return $this->format_failed_numbers();
	}

	private function send_normal_sms() {
		$params = [
			'lineNumber'   => $this->senderNumber,
			'messageText'  => $this->message,
			'mobiles'      => $this->mobile,
			'sendDateTime' => null,
		];

		$headers = [
			'Content-Type' => 'application/json',
			'X-API-KEY'    => $this->api_key,
		];

		$remote = wp_remote_post( $this->api_url . 'send/bulk', [
			'method'      => 'POST',
			'body'        => json_encode( $params ),
			'headers'     => $headers,
			'timeout'     => 5,
			'data_format' => 'body',
		] );

		if ( is_wp_error( $remote ) ) {
			return 'خطا: ' . $remote->get_error_message();
		}

		$response_message = wp_remote_retrieve_response_message( $remote );
		$response_code    = wp_remote_retrieve_response_code( $remote );

		if ( empty( $response_code ) || 200 != $response_code ) {
			return $response_code . ' -> ' . $response_message;
		}

		$response_body = wp_remote_retrieve_body( $remote );

		if ( empty( $response_body ) ) {
			return 'پاسخی از وب‌سرویس دریافت نشد.';
		}

		$response_data = json_decode( $response_body, true );

		if ( ! empty( json_last_error() ) ) {
			return 'قالب پاسخ دریافتی از وب‌سرویس نامعتبر است.';
		}

		if ( isset( $response_data['status'] ) && $response_data['status'] == '1' ) {
			return true;
		}

		return isset( $response_data['status'] ) ? $response_data['status'] . ' : ' . $response_data['message'] : 'خطایی ناشناخته رخ داده است.';
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
