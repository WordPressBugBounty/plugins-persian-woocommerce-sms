<?php

namespace PW\PWSMS\Gateways;


use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SMSIRNew implements GatewayInterface {
	use GatewayTrait;

	public string $api_url = 'https://api.sms.ir/v1/';
	public string $api_key;

	public static function id() {
		return 'smsir-new';
	}

	public static function name() {
		return 'SMS.ir (جدید)';
	}


	public function send() {
		$this->username     = trim( $this->username );
		$this->password     = trim( $this->password );
		$this->senderNumber = trim( $this->senderNumber );

		$this->api_key = ! empty( $this->username ) ? $this->username : $this->password;

		if ( empty( $this->api_key ) ) {
			return false;
		}

		// Replace "pcode" with "patterncode" in the message
		$this->message = str_replace( 'pcode', 'patterncode', $this->message );

		// Determine if it's a pattern-based message
		if ( substr( $this->message, 0, 11 ) === "patterncode" ) {
			// Handle pattern-based message
			return $this->send_pattern_sms();
		} else {
			// Handle simple SMS
			return $this->send_simple_sms();
		}
	}

	private function send_pattern_sms() {
		// Replace new lines with semicolons and split
		$message_content = str_replace( [ "\r\n", "\n" ], ';', $this->message );
		$message_parts   = explode( ';', $message_content );
		$pattern_code    = explode( ':', $message_parts[0] )[1];
		unset( $message_parts[0] ); // Remove the first element containing the pattern code

		// Initialize the pattern data array
		$pattern_data = [];
		foreach ( $message_parts as $parameter ) {
			$split_parameter = explode( ':', $parameter, 2 );
			if ( count( $split_parameter ) === 2 ) {
				// Add each parameter as an object with 'name' and 'value'
				$pattern_data[] = [
					"name"  => trim( $split_parameter[0] ),
					"value" => trim( $split_parameter[1] )
				];
			}
		}

		$api_url = $this->api_url . "send/verify";

		$headers = [
			'Content-Type' => 'application/json',
			'x-api-key'    => $this->api_key,
		];

		$failed_numbers = [];

		foreach ( $this->mobile as $recipient ) {
			$post_fields = json_encode( [
				"mobile"     => $recipient,
				"templateId" => $pattern_code,
				"parameters" => $pattern_data
			] );

			$remote = wp_remote_post( $api_url, [
				'method'      => 'POST',
				'body'        => $post_fields,
				'headers'     => $headers,
				'timeout'     => 5,
				'data_format' => 'body'
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

			$response_body = wp_remote_retrieve_body( $remote );

			if ( empty( $response_body ) ) {
				$failed_numbers[ $recipient ] = 'بدون پاسخ دریافتی از سمت وب سرویس.';
				continue;
			}

			$response_data = json_decode( $response_body, true );

			if ( ! empty( json_last_error() ) ) {
				$failed_numbers[ $recipient ] = 'فرمت نامعتبر پاسخ از سمت وب سرویس.';
				continue;
			}

			if ( ! isset( $response_data['status'] ) && $response_data['status'] = ! '1' ) {
				$error_message                = $response_data['message'] ?? 'خطای نامشخص';
				$failed_numbers[ $recipient ] = $error_message;
				continue;
			}

			if ( isset( $response_data['status'] ) && $response_data['status'] == '1' ) {
				continue;
			}

		}

		// Handle failed numbers and format response
		return $this->format_failed_numbers( $failed_numbers );
	}

	public function send_simple_sms() {
		$params = [
			'lineNumber'   => $this->senderNumber,
			'messageText'  => $this->message,
			'mobiles'      => $this->mobile,
			'sendDateTime' => null
		];

		$api_url = $this->api_url . 'send/bulk';

		$headers = [
			'Content-Type' => 'application/json',
			'X-API-KEY'    => $this->api_key
		];

		$remote = wp_remote_post( $api_url, [
			'method'      => 'POST',
			'body'        => json_encode( $params ),
			'headers'     => $headers,
			'timeout'     => 5,
			'data_format' => 'body'
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
			return 'بدون پاسخ دریافتی از سمت وب سرویس.';
		}

		$response_data = json_decode( $response_body, true );

		if ( ! empty( json_last_error() ) ) {
			return 'فرمت نامعتبر پاسخ از سمت وب سرویس.';
		}

		if ( isset( $response_data['status'] ) && $response_data['status'] == '1' ) {
			return true;
		}

		return isset( $response_data['status'] ) ? $response_data['status'] . ' : ' . $response_data['message'] : 'خطای نامشخص';
	}


	private function format_failed_numbers( array $failed_numbers ) {
		// Handle failed numbers and format response
		if ( empty( $failed_numbers ) ) {
			return true;
		}

		$grouped = [];

		foreach ( $failed_numbers as $number => $message ) {

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