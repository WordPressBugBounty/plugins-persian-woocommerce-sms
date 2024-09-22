<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;

class MaxSMSPattern implements GatewayInterface {
	use GatewayTrait;

	public string $api_url = 'https://api2.ippanel.com/api/v1';

	public static function id() {
		return 'ippanel';
	}

	public static function name() {
		return 'ippanel.co (maxsms.co) خدماتی پترن';
	}

	public function send() {

		$pattern_api_url = $this->api_url . '/sms/pattern/normal/send';
		$api_key         = ! empty( $this->username ) ? $this->username : $this->password;
		$recipients      = $this->mobile;
		/**
		 * Message in pattern is :
		 * templateID:template_id|key1:value1|key2:value2
		 */
		$message = $this->message;

		$from = $this->senderNumber;
		if ( empty( $from ) ) {
			$from = '+983000505';
		}

		// Extract templateID number
		if ( preg_match( '/templateID:([^|:\s]+)/', $message, $matches ) ) {
			$template_id = $matches[1]; // The extracted templateID number
		} else {
			return 'بدون templateID:';
		}

		// Remove the line containing templateID
		$message = preg_replace( '/templateID:([^|:\s]+)|/', '', $message );

		// extract other pairs => key1:value1|key2:value2
		$pairs = array_filter( explode( '|', $message ) );

		// Process each pair to create key-value arrays
		$key_value_pairs = array_map( function ( $pair ) {
			[ $key, $value ] = explode( ':', trim( $pair ) );

			return [ trim( $key ), is_numeric( $value ) ? $value + 0 : trim( $value ) ];
		}, $pairs );

		// Convert the array of arrays into an associative array
		$variable = array_column( $key_value_pairs, 1, 0 );


		if ( empty( $api_key ) || empty( $message ) || empty( $recipients ) ) {
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
				'code'      => $template_id,
				'sender'    => $from,
				'recipient' => $recipient,
				'variable'  => $variable,
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
				// This is the success section, should Anything happen here?
				continue;
			}
		}

		if ( ! empty( $failed_numbers ) ) {
			// Group numbers by their messages
			$grouped = [];
			foreach ( $failed_numbers as $number => $message ) {
				if ( ! isset( $grouped[ $message ] ) ) {
					$grouped[ $message ] = [];
				}
				$grouped[ $message ][] = $number;
			}

			// Format the grouped data
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
}
