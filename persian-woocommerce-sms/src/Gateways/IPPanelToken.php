<?php

namespace PW\PWSMS\Gateways;

/**
 * The new IPPanel service based on https://ippanelcom.github.io
 * Can send Pattern and Simple SMS at same time (based on message)
 */
class IPPanelToken extends Gateway {

	/**
	 * @var string
	 */
	public string $api_url = 'https://edge.ippanel.com/v1/';

	/**
	 * @var array
	 */
	public array $failed_numbers = [];

	/**
	 * @var string
	 */
	private string $api_key = '';

	public static function id(): string {
		return 'ippanel-token';
	}

	public static function name(): string {
		return 'ippanel.com (کلید دسترسی)';
	}

	public function send() {
		$this->api_key = $this->get_token();

		if ( empty( $this->api_key ) ) {
			return 'کلید وبسرویس را در بخش تنظیمات وبسرویس تعریف کنید.';
		}

		if ( empty( $this->senderNumber ) ) {
			return 'شماره فرستنده پیامک تعیین نشده است.';
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
			'sending_type' => 'pattern',
			'from_number'  => $this->senderNumber,
			'code'         => $pattern['code'],
			'params'       => $pattern['vars'],
		];

		foreach ( $this->mobile as $recipient ) {

			$payload['recipients'] = [ $recipient ];

			$response = wp_remote_post( $this->api_url . 'api/send', [
				'method'  => 'POST',
				'body'    => json_encode( $payload ),
				'timeout' => 10,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => $this->api_key,
				],
			] );

			$this->handle_response( $response, $recipient );
		}
	}

	private function send_normal_sms() {

		$payload = [
			'sending_type' => 'webservice',
			'from_number'  => $this->senderNumber,
			'message'      => $this->message,
			'params'       => [
				'recipients' => $this->mobile,
			],
		];

		$response = wp_remote_post( $this->api_url . 'api/send', [
			'method'  => 'POST',
			'body'    => json_encode( $payload ),
			'timeout' => 10,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => $this->api_key,
			],
		] );

		$this->handle_response( $response );
	}

	private function handle_response( $response, string $recipient = '' ): void {

		if ( is_wp_error( $response ) ) {

			$message = $response->get_error_message();

			if ( $recipient ) {
				$this->failed_numbers[ $recipient ] = $message;
			} else {
				$this->failed_numbers[] = $message;
			}

			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['meta']['status'] ) && $body['meta']['status'] ) {
			return;
		}

		if ( isset( $body['meta']['message'] ) ) {

			$message = $body['meta']['message'];

		} elseif ( isset( $body['meta']['errors'] ) && is_array( $body['meta']['errors'] ) ) {

			$all_errors = [];

			foreach ( $body['meta']['errors'] as $field_errors ) {

				if ( ! is_array( $field_errors ) ) {
					continue;
				}

				$all_errors = array_merge( $all_errors, $field_errors );

			}

			$message = implode( ' ', $all_errors );

		} else {

			$message = 'خطایی ناشناخته رخ داده است.';

		}

		if ( ! empty( $recipient ) ) {
			$this->failed_numbers[ $recipient ] = $message;
		} else {
			$this->failed_numbers[] = $message;
		}
	}


	private function format_failed_numbers(): bool {

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
