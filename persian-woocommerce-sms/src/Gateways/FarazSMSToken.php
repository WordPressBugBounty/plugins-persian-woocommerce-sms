<?php

namespace PW\PWSMS\Gateways;

class FarazSMSToken extends Gateway {

	public string $api_url = 'https://api.iranpayamak.com';
	public array $failed_numbers = [];
	public string $api_key;

	public static function id(): string {
		return 'farazsms';
	}

	public static function name(): string {
		return 'FarazSMS.com - فراز اس ام اس';
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

	public function send_pattern_sms() {
		$pattern = $this->parse_pattern();

		$payload = [
			'code'          => $pattern['code'],
			'attributes'    => $pattern['vars'],
			'line_number'   => $this->senderNumber,
			'from'          => $this->senderNumber,
			'number_format' => 'english',
			'numberFormat'  => 'english',
		];

		// Todo: in https://docs.iranpayamak.com/send-simple-sms-13909967e0 with server respond has difference so from and number_format provided with both keys
		foreach ( $this->mobile as $recipient ) {

			$clean_number = trim( $recipient );

			if ( str_starts_with( $clean_number, '+98' ) ) {
				$clean_number = substr( $clean_number, 3 );
			} elseif ( str_starts_with( $clean_number, '098' ) ) {
				$clean_number = substr( $clean_number, 3 );
			} elseif ( str_starts_with( $clean_number, '98' ) ) {
				$clean_number = substr( $clean_number, 2 );
			}
			
			if ( ! str_starts_with( $clean_number, '0' ) ) {
				$clean_number = '0' . $clean_number;
			}

			$payload['recipient'] = $clean_number;

			$response = wp_remote_post( $this->api_url . '/ws/v1/sms/pattern',
				[
					'method'  => 'POST',
					'body'    => json_encode( $payload ),
					'timeout' => 8,
					'headers' => [
						'Content-Type' => 'application/json',
						'Accept'       => 'application/json',
						'Api-Key'      => $this->api_key,
					],
				]
			);

			$this->handle_response( $response, $recipient );
		}
	}

	public function send_normal_sms() {
		$payload = [
			'text'          => $this->message,
			'recipients'    => $this->mobile,
			'from'          => $this->senderNumber,
			'line_number'   => $this->senderNumber,
			'number_format' => 'english',
		];

		$args = [
			'headers' => [
				'Accept'  => 'application/json',
				'Api-Key' => $this->api_key,
			],
			'body'    => $payload,
		];

		$response = wp_remote_post( $this->api_url . '/ws/v1/sms/simple', $args );

		$this->handle_response( $response );
	}

	public function handle_response( $response, string $recipient = '' ): void {

		if ( is_wp_error( $response ) ) {

			$message = $response->get_error_message();

			if ( $recipient ) {
				$this->failed_numbers[ $recipient ] = $message;
			} else {
				$this->failed_numbers[] = $message;
			}

			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 && $code !== 201 ) {

			$message = isset( $body['messages'] )
				? ( is_array( $body['messages'] ) ? implode( ', ', $body['messages'] ) : $body['messages'] )
				: 'خطای HTTP: ' . $code;

			if ( $recipient ) {
				$this->failed_numbers[ $recipient ] = $message;
			} else {
				$this->failed_numbers[] = $message;
			}

			return;
		}

		if ( isset( $body['status'] ) && $body['status'] !== 'success' ) {

			$message = isset( $body['messages'] )
				? ( is_array( $body['messages'] ) ? implode( ', ', $body['messages'] ) : $body['messages'] )
				: 'ارسال پیامک ناموفق بود.';

			if ( $recipient ) {
				$this->failed_numbers[ $recipient ] = $message;
			} else {
				$this->failed_numbers[] = $message;
			}

		}
	}

}
