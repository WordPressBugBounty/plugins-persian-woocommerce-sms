<?php

namespace PW\PWSMS\Gateways;

class PayamResanPattern extends Gateway {

	public string $api_url = 'https://api.sms-webservice.com/api/V3';
	public string $api_key;
	public array $failed_numbers;

	public static function id(): string {
		return 'payamresan-pattern';
	}

	public static function name(): string {
		return 'payam-resan.com (پترن)';
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

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];

		$payload = [
			'ApiKey'      => $this->api_key,
			'TemplateKey' => $pattern['code'],
		];

		$payload = array_merge( $payload, $pattern['vars'] );

		foreach ( $this->mobile as $recipient ) {

			$payload['Destination'] = $recipient;

			$remote = wp_remote_post( $this->api_url . '/SendTokenSingle', [
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
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

			$response = wp_remote_retrieve_body( $remote );

			if ( empty( $response ) ) {
				$this->failed_numbers[ $recipient ] = 'پاسخی از وب‌سرویس دریافت نشد.';
				continue;
			}

			$response_data = json_decode( $response, true );

			if ( ! empty( json_last_error() ) ) {
				$this->failed_numbers[ $recipient ] = 'قالب پاسخ دریافتی از وب‌سرویس نامعتبر است.';
				continue;
			}

			if ( isset( $response_data['id'] ) ) {
				continue;
			}

			$this->failed_numbers[ $recipient ] = 'خطای وبسرویس: ' . $response_data['Error'] ?? 'خطایی ناشناخته رخ داده است.';
		}

		return $this->format_failed_numbers();
	}

	private function send_normal_sms() {

		$payload = [
			'ApiKey'     => $this->api_key,
			'Text'       => $this->message,
			'Sender'     => $this->senderNumber,
			'Recipients' => $this->mobile,
		];

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];

		$remote = wp_remote_post( $this->api_url . '/SendBulk', [
			'headers' => $headers,
			'body'    => wp_json_encode( $payload ),
		] );

		if ( is_wp_error( $remote ) ) {
			return $remote->get_error_message();
		}

		$response_message = wp_remote_retrieve_response_message( $remote );
		$response_code    = wp_remote_retrieve_response_code( $remote );

		if ( empty( $response_code ) || 200 != $response_code ) {
			return $response_code . ' -> ' . $response_message;
		}

		$response = wp_remote_retrieve_body( $remote );

		if ( empty( $response ) ) {
			return 'پاسخی از وب‌سرویس دریافت نشد.';
		}

		$response_data = json_decode( $response, true );

		if ( ! empty( json_last_error() ) ) {
			return 'قالب پاسخ دریافتی از وب‌سرویس نامعتبر است.';
		}

		if ( isset( $response_data['Success'] ) && $response_data['Success'] ) {
			return true;
		}

		return 'خطای وبسرویس: ' . $response_data['Error'] ?? 'خطایی ناشناخته رخ داده است.';
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

		$result = implode( ', ', array_map( function ( string $message, array $numbers ) {
			return implode( ',', $numbers ) . ': ' . $message;
		}, array_keys( $grouped ), $grouped ) );

		return $result;
	}
}
