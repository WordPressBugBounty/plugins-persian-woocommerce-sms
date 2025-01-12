<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class LogisticSMS implements GatewayInterface {
	use GatewayTrait;

	private $token;

	private $url = 'https://api.logisticsms.ir';

	private $transient = 'pwsms_logistic_sms_token';

	public static function id() {
		return 'logistic-sms';
	}

	public static function name() {
		return 'logisticsms.ir';
	}

	public function get_account_balance() {
		$url    = $this->url . '/api/v1/account/info';
		$header = [ 'X-API-TOKEN' => $this->token ];
		$remote = wp_remote_get( $url, [
			'headers' => $header
		] );

		if ( is_wp_error( $remote ) ) {
			return 0;
		}

		$response      = wp_remote_retrieve_body( $remote );
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( empty( $response_code ) || 200 != $response_code ) {
			return 0;
		}

		$response = json_decode( $response, true );

		return $response['response']['data']['sms_charge'] ?? 0;

	}

	public function send() {
		$token = $this->get_token();

		if ( empty( $token ) ) {

			$fetch_token_result = $this->fetch_token();

			if ( $fetch_token_result !== true ) {
				return $fetch_token_result;
			}

			$token = $this->get_token();

		}


		if ( empty( $token ) ) {
			return 'توکن امنیتی ارسال پیام دریافت نشد.';
		}


		$url = $this->url . '/api/v1/sms/send';

		$failed_numbers = [];

		foreach ( $this->mobile as $mobile ) {

			// Prepare the payload
			$payload = [
				'receptor' => $mobile,
				'message'  => $this->message,
				'sender'   => $this->senderNumber
			];

			$headers = [
				'X-API-TOKEN' => $token,
			];

			// Make the POST request using wp_remote_post
			$remote = wp_remote_post( $url, [
				'method'    => 'POST',
				'body'      => $payload ,
				'timeout'   => 5,
				'headers'   => $headers,
				'sslverify' => false
			] );

			// Check if the response is a WP_Error
			if ( is_wp_error( $remote ) ) {
				$failed_numbers[ $mobile ] = $remote->get_error_message();
			}

			$response_message = wp_remote_retrieve_response_message( $remote );
			$response_code    = wp_remote_retrieve_response_code( $remote );

			if ( empty( $response_code ) || 200 != $response_code ) {
				$failed_numbers[ $mobile ] = $response_code . ' -> ' . $response_message;
				continue;
			}

			// Get the response body and decode the JSON
			$remote_body = wp_remote_retrieve_body( $remote );

			$response = json_decode( $remote_body, true );

			// Check if 'msg' is 'success'
			if ( ! isset( $response['msg'] ) || $response['msg'] !== 'success' ) {
				$failed_numbers[ $mobile ] = 'خطا: ' . $response['msg'];
				continue;
			}

		}

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

			$result = implode( ', ', array_map( function ( string $message, array $numbers ) {
				return implode( ',', $numbers ) . ': ' . $message;
			}, array_keys( $grouped ), $grouped ) );

			return $result;
		}

		return true;
	}

	/**
	 * Retrieve stored transient token stored in WordPress
	 * @return string
	 */
	private function get_token() {
		return get_transient( $this->transient );
	}

	/**
	 * Method to store token in WordPress transient
	 * returns string if failed, true if success
	 * @return string | bool
	 */
	private function fetch_token() {
		$url      = "{$this->url}/api/v1/login";
		$username = $this->username;
		$password = $this->password;
		if ( empty( $username ) || empty( $password ) ) {
			return 'نام کاربری یا رمز عبور وارد نشده.';
		}

		$payload = [
			'username' => $username,
			'password' => $password,
		];

		$payload = http_build_query( $payload );

		// Make the POST request to log in
		$response = wp_remote_post( $url, [
			'body'    => $payload,
			'timeout' => 10,
			'headers' => [
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
		] );

		// Check if the response is a WP_Error
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		// Get the response body and decode the JSON
		$body = wp_remote_retrieve_body( $response );

		$json_response = json_decode( $body, true );

		// Check error message existence
		if ( ! isset( $json_response['msg'] ) || $json_response['msg'] !== 'success' ) {
			return $json_response['msg'] ?? 'خطا در دریافت توکن هویتی';
		}

		// Token and expiration data from response
		$token      = $json_response['data']['token'];
		$expired_at = $json_response['data']['expired_at'];

		// Convert the expiration date to a timestamp
		$expiration_timestamp = strtotime( $expired_at );
		$current_time         = time();

		// Calculate the expiration in seconds
		$expiration_in_seconds = $expiration_timestamp - $current_time;

		// If the expiration time is valid
		if ( ! ( $expiration_in_seconds > 0 ) ) {
			return "تاریخ نامعتبر انقضای توکن.";
		}

		// Store the token in a transient
		set_transient( $this->transient, $token, $expiration_in_seconds );

		return true;
	}
}
