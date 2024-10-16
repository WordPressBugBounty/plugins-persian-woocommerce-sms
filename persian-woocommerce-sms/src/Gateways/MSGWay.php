<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class MSGWay implements GatewayInterface {
	use GatewayTrait;

	public static function id() {
		return 'msgway';
	}

	public static function name() {
		return 'msgway.com';
	}

	public function send() {
		$api_key = ! empty( $this->username ) ? $this->username : $this->password;

		if ( empty( $api_key ) ) {
			return false;
		}

		$from    = $this->senderNumber;
		$mobiles = $this->mobile;
		$message = $this->message;

		// Extract templateID number
		if ( preg_match( '/templateID:(\d+)/', $message, $matches ) ) {
			$template_id = $matches[1]; // The extracted templateID number
		} else {
			return 'بدون templateID:';
		}

		// Remove the line containing templateID
		$message = preg_replace( '/templateID:\d+\|/', '', $message );

		$results        = [];
		$failed_numbers = [];
		$message_params = array_filter( explode( '|', $message ) );

		// Iterate over message_params and ensure large numbers are formatted as strings
		foreach ( $message_params as &$param ) {
			// Check if the param is numeric and is large enough to potentially be scientific notation
			if ( is_numeric( $param ) && strpos( $param, 'E' ) !== false ) {
				// Convert the number into a string without scientific notation
				$param = sprintf( '%0.0f', (float) $param );
			}
		}

		foreach ( $mobiles as $mobile ) {
			try {
				$params = [
					"mobile"     => $mobile,
					"method"     => "sms",
					"templateID" => (int) $template_id,
					"params"     => $message_params,
				];

				if ( ! empty( $from ) ) {
					$params['provider'] = (int) $from;
				}

				$params = json_encode( $params, JSON_UNESCAPED_UNICODE );

				$response = wp_remote_post( 'https://api.msgway.com/send', [
					'body'    => $params,
					'headers' => [
						'Content-Type' => 'application/json',
						'apiKey'       => $api_key,
					]
				] );

				if ( is_wp_error( $response ) ) {
					throw new Exception( $response->get_error_message(), $response->get_error_code() );
				}

				$http_status = wp_remote_retrieve_response_code( $response );
				if ( $http_status != 200 ) {
					throw new Exception( "HTTP request failed with status code " . $http_status, $http_status );
				}

				$response_body    = wp_remote_retrieve_body( $response );
				$decoded_response = json_decode( $response_body, true );

				if ( $decoded_response ) {
					if ( $decoded_response['status'] === 'success' ) {
						$results[] = [
							'mobile'      => $mobile,
							'status'      => 'success',
							'referenceID' => $decoded_response['referenceID']
						];
					} else {
						$error_message    = $decoded_response['error']['message'] ?? 'Unknown error';
						$results[]        = [
							'mobile'  => $mobile,
							'status'  => 'error',
							'message' => $error_message
						];
						$failed_numbers[] = $mobile;
					}
				} else {
					throw new Exception( 'Failed to decode JSON response' );
				}

			} catch ( Exception $e ) {
				// Handle exceptions
				$results[]        = [
					'mobile'  => $mobile,
					'status'  => 'error',
					'message' => $e->getMessage()
				];
				$failed_numbers[] = $mobile;
			}
		}

		// Check if there are any failed messages
		if ( empty( $failed_numbers ) ) {
			return true; // All messages were sent successfully
		} else {
			$failed_numbers_list = implode( ', ', $failed_numbers );

			return "پیام به این شماره (ها) ارسال نشد: " . $failed_numbers_list;
		}
	}
}
