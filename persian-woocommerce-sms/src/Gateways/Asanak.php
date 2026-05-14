<?php

namespace PW\PWSMS\Gateways;

class Asanak extends Gateway {

	public string $api_url = 'https://sms.asanak.ir/webservice';
	public array $failed_numbers = [];

	public static function id(): string {
		return 'asanak';
	}

	public static function name(): string {
		return 'Asanak.ir - آسانک';
	}

	public function send() {

		if ( empty( $this->username ) || empty( $this->password ) ) {
			return 'نام کاربری یا رمز عبور خالی است.';
		}

		if ( $this->is_pattern() ) {
			$this->send_pattern_sms();
		} else {
			$this->send_normal_sms();
		}

		return $this->format_failed_numbers();
	}

	private function send_pattern_sms() {

		$pattern = $this->parse_pattern();

		$payload = [
			'username'          => $this->username,
			'password'          => $this->password,
			'template_id'       => $pattern['code'],
			'parameters'        => $pattern['vars'],
			'send_to_blacklist' => 0,
		];

		foreach ( $this->mobile as $recipient ) {

			$payload['destination'] = $this->normalize_number( $recipient );

			$response = wp_remote_post( $this->api_url . '/v2rest/template', [
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => json_encode( $payload ),
				'timeout' => 8,
			] );

			$this->handle_response( $response, $recipient );

		}

	}

	private function send_normal_sms() {

		$response = wp_remote_post( $this->api_url . '/v2rest/sendsms', [
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body'    => [
				'username'          => $this->username,
				'password'          => $this->password,
				'source'            => $this->senderNumber,
				'destination'       => implode( ',', array_map( [ $this, 'normalize_number', ], $this->mobile ) ),
				'message'           => $this->message,
				'send_to_blacklist' => 0,
			],
			'timeout' => 8,
		] );

		$this->handle_response( $response );
	}

	private function normalize_number( $number ): string {

		return str_replace( '+98', '0', trim( $number ) );
	}

	private function handle_response( $response, $recipient = '' ) {

		if ( is_wp_error( $response ) ) {

			$message = $response->get_error_message();
			$this->record_failure( $recipient, $message );

			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['meta']['status'] ) || $body['meta']['status'] != 200 ) {

			$message = $body['meta']['message'] ?? 'پاسخ نامعتبر از سمت وبسرویس.';
			$this->record_failure( $recipient, $message );

		}
	}

	private function record_failure( $recipient, $message ) {

		if ( $recipient ) {
			$this->failed_numbers[ $recipient ] = $message;
		} else {
			$this->failed_numbers[] = $message;
		}

	}

	private function format_failed_numbers() {

		if ( empty( $this->failed_numbers ) ) {
			return true;
		}

		$grouped = [];

		foreach ( $this->failed_numbers as $number => $message ) {
			$grouped[ $message ][] = $number;
		}

		return implode(
			', ',
			array_map(
				fn( $message, $numbers ) => implode( ', ', (array) $numbers ) . ': ' . $message,
				array_keys( $grouped ),
				$grouped
			)
		);
	}

	public function get_credit() {
		try {
			$ch = curl_init();

			curl_setopt_array( $ch, [
				CURLOPT_URL            => $this->api_url . '/v2rest/getrialcredit',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST           => true,
				CURLOPT_HTTPHEADER     => [ 'Content-Type: application/json' ],
				CURLOPT_POSTFIELDS     => json_encode( [
					'username' => $this->username,
					'password' => $this->password,
				] ),
			] );

			$response = curl_exec( $ch );

			if ( curl_errno( $ch ) ) {
				throw new \Exception( curl_error( $ch ) );
			}

			curl_close( $ch );

			$data = json_decode( $response, true );

			if ( ! isset( $data['meta']['status'] ) ) {
				throw new \Exception( 'پاسخ نامعتبر از سمت سرور دریافت شد.' );
			}

			if ( (int) $data['meta']['status'] !== 200 ) {
				return 'خطا در دریافت موجودی: ' . ( $data['meta']['message'] ?? 'Unknown error' );
			}

			return isset( $data['data']['credit'] ) ? (int) $data['data']['credit'] : 0;

		} catch ( \Exception $e ) {
			return 'خطا در دریافت موجودی: ' . $e->getMessage();
		}
	}
}
