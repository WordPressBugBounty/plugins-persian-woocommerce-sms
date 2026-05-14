<?php

namespace PW\PWSMS\Gateways;

use DateTime;
use DateTimeZone;

class MaxSMS extends Gateway {

	public string $api_url = 'https://api2.ippanel.com/api/v1';
	public string $api_key = '';
	public array $failed_numbers = [];

	public static function id(): string {
		return 'maxsms';
	}

	public static function name(): string {
		return 'MaxSMS.co - مکس اس ام اس';
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
			return $this->send_pattern_sms();
		} else {
			return $this->send_normal_sms();
		}
	}

	private function send_pattern_sms() {

		$pattern = $this->parse_pattern();

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'apikey'       => $this->api_key,
		];

		$payload = [
			'code'     => $pattern['code'],
			'sender'   => $this->senderNumber,
			'variable' => $pattern['vars'],
		];

		foreach ( $this->mobile as $recipient ) {

			$payload['recipient'] = $recipient;

			$remote               = wp_remote_post( $this->api_url . '/sms/pattern/normal/send', [
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

			if ( isset( $response_data['status'] ) && strtolower( $response_data['status'] ) == 'ok' ) {
				continue;
			}

		}

		return $this->format_failed_numbers();
	}

	private function send_normal_sms() {

		$date_time_now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$date_time_now->modify( '+30 seconds' );
		$date_time = $date_time_now->format( 'Y-m-d\TH:i:s.v\Z' );

		$payload = [
			'recipient' => $this->mobile,
			'sender'    => $this->senderNumber,
			'message'   => $this->message,
			'time'      => $date_time,
		];

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'apikey'       => $this->api_key,
		];

		$remote = wp_remote_post( $this->api_url . '/sms/send/webservice/single', [
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

		if ( isset( $response_data['status'] ) && strtolower( $response_data['status'] ) == 'ok' ) {
			return true;
		}

		return $response;
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

		return implode( ', ', array_map(
			function ( string $message, array $numbers ) {
				return implode( ',', $numbers ) . ': ' . $message;
			},
			array_keys( $grouped ),
			$grouped
		) );
	}
}
