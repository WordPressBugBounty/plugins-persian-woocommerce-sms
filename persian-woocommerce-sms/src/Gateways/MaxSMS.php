<?php

namespace PW\PWSMS\Gateways;

use DateTime;
use DateTimeZone;
use PW\PWSMS\PWSMS;

class MaxSMS implements GatewayInterface {
	use GatewayTrait;

	public string $api_url = 'https://api2.ippanel.com/api/v1';

	public static function id() {
		return 'maxsms';
	}

	public static function name() {
		return 'maxsms.co';
	}

	public function send() {
		$recipients      = $this->mobile;
		$api_key         = ! empty( $this->username ) ? trim( $this->username ) : trim( $this->password );
		$from            = trim( $this->senderNumber );
		$message_content = $this->message;

		// Default sender number if not provided
		if ( empty( $from ) ) {
			$from = '+983000505';
		}

		// Replace "pcode" with "patterncode" in the message
		$message_content = str_replace( 'pcode', 'patterncode', $message_content );

		// Determine if it's a pattern-based message
		if ( substr( $message_content, 0, 11 ) === "patterncode" ) {
			// Handle pattern-based message
			return $this->send_pattern_sms( $recipients, $from, $message_content, $api_key );
		} else {
			// Handle simple SMS
			return $this->send_simple_sms( $recipients, $from, $message_content, $api_key );
		}
	}

	private function send_pattern_sms( array $recipients, string $from, string $message_content, string $api_key ) {
		$pattern_api_url = $this->api_url . '/sms/pattern/normal/send';

		// Replace "pcode" with "patterncode" in the message
		$message_content = str_replace( 'pcode', 'patterncode', $message_content );

		// Replace new lines with semicolons and split
		$message_content = str_replace( [ "\r\n", "\n" ], ';', $message_content );
		$message_parts   = explode( ';', $message_content );
		$pattern_code    = explode( ':', $message_parts[0] )[1];
		unset( $message_parts[0] ); // Remove the first element containing the pattern code

		// Initialize the pattern data array
		$pattern_data = [];
		foreach ( $message_parts as $parameter ) {
			$split_parameter = explode( ':', $parameter, 2 ); // Split only on the first occurrence
			if ( count( $split_parameter ) === 2 ) { // Ensure both key and value exist
				$pattern_data[ trim( $split_parameter[0] ) ] = trim( $split_parameter[1] );
			}
		}

		// Check for required fields
		if ( empty( $api_key ) || empty( $pattern_code ) || empty( $recipients ) ) {
			return 'اطلاعات پنل، یا پیامک به درستی وارد نشده.';
		}

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'apikey'       => $api_key,
		];

		$failed_numbers = [];

		foreach ( $recipients as $recipient ) {
			$data = [
				'code'      => trim( $pattern_code ),
				'sender'    => $from,
				'recipient' => $recipient,
				'variable'  => $pattern_data,
			];

			$remote = wp_remote_post( $pattern_api_url, [
				'headers' => $headers,
				'body'    => wp_json_encode( $data ),
			] );

			if ( is_wp_error( $remote ) ) {
				$failed_numbers[ $recipient ] = $remote->get_error_message();
			}

			$response_message = wp_remote_retrieve_response_message( $remote );
			$response_code    = wp_remote_retrieve_response_code( $remote );

			if ( empty( $response_code ) || 200 != $response_code ) {
				$failed_numbers[ $recipient ] = $response_code . ' -> ' . $response_message;
				continue;
			}

			$response = wp_remote_retrieve_body( $remote );

			if ( empty( $response ) ) {
				$failed_numbers[ $recipient ] = 'بدون پاسخ دریافتی از سمت وب سرویس.';
				continue;
			}

			$response_data = json_decode( $response, true );
			if ( ! empty( json_last_error() ) ) {
				$failed_numbers[ $recipient ] = 'فرمت نامعتبر پاسخ از سمت وب سرویس.';
				continue;
			}

			if ( isset( $response_data['status'] ) && strtolower( $response_data['status'] ) == 'ok' ) {
				continue;
			}
		}

		// Handle failed numbers and format response
		return $this->format_failed_numbers( $failed_numbers );
	}

	private function format_failed_numbers( array $failed_numbers ) {
		// Handle failed numbers and format response
		if ( ! empty( $failed_numbers ) ) {
			$grouped = [];
			foreach ( $failed_numbers as $number => $message ) {
				if ( ! isset( $grouped[ $message ] ) ) {
					$grouped[ $message ] = [];
				}
				$grouped[ $message ][] = $number;
			}

			$result = implode( ', ', array_map(
				function ( string $message, array $numbers ) {
					return implode( ',', $numbers ) . ': ' . $message;
				},
				array_keys( $grouped ),
				$grouped
			) );

			return $result;
		}

		return true;
	}

	private function send_simple_sms( array $recipients, string $from, string $message_content, string $api_key ) {
		$single_api_url = $this->api_url . '/sms/send/webservice/single';

		// Check for required fields
		if ( empty( $api_key ) || empty( $message_content ) || empty( $recipients ) ) {
			return 'اطلاعات پنل، یا پیامک به درستی وارد نشده.';
		}

		$date_time_now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$date_time_now->modify( '+30 seconds' );
		$date_time = $date_time_now->format( 'Y-m-d\TH:i:s.v\Z' );

		$data = [
			'recipient' => $recipients,
			'sender'    => $from,
			'message'   => $message_content,
			'time'      => $date_time
		];

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'apikey'       => $api_key,
		];

		$remote = wp_remote_post( $single_api_url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $data ),
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
			return 'بدون پاسخ دریافتی از سمت وب سرویس.';
		}

		$response_data = json_decode( $response, true );

		if ( ! empty( json_last_error() ) ) {
			return 'فرمت نامعتبر پاسخ از سمت وب سرویس.';
		}

		if ( isset( $response_data['status'] ) && strtolower( $response_data['status'] ) == 'ok' ) {
			return true;
		}

		return $response;
	}
}
