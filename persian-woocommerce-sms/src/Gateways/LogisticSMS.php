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

	public function send() {
		$token            = $this->get_token();
		$token_validation = true;

		if ( empty( $token ) ) {
			$token_validation = $this->fetch_token();
		}

		if ( empty( $token ) && $token_validation === true ) {
			$token = $this->get_token();
		}

		if ( empty( $token ) ) {
			return 'توکن امنیتی ارسال پیام دریافت نشد.';
		}

		$url = "{$this->url}/api/v1/sms/batch";

		// Prepare the payload
		$payload = [
			'receptors' => $this->mobile,
			'message'   => $this->message,
			'sender'    => $this->senderNumber
		];

		// Prepare the headers, including the token for authentication
		$headers = [
			'X-API-TOKEN'  => $token,  // Set the token in headers
			'Content-Type' => 'application/x-www-form-urlencoded'
		];

		// Make the POST request using wp_remote_post
		$response = wp_remote_post( $url, [
			'body'    => $payload,
			'timeout' => 45,
			'headers' => $headers,
		] );

		// Check if the response is a WP_Error
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		// Get the response body and decode the JSON
		$body          = wp_remote_retrieve_body( $response );
		$json_response = json_decode( $body, true );

		// Check if 'msg' is 'success'
		if ( ! isset( $json_response['msg'] ) || $json_response['msg'] !== 'success' ) {
			return 'خطا: ' . $json_response['msg'];
		}

		$failed_numbers = [];
		// Process the SMS status reports
		foreach ( $json_response['data'] as $sms_status ) {
			// Check if the status indicates failure
			if ( ! $sms_status['success'] ) {
				// Store the masked number in the array if the SMS failed
				$failed_numbers[] = $sms_status['destNumber'];
			}
		}

		if ( ! empty( $failed_numbers ) ) {
			$failed_numbers_list = implode( ', ', $failed_numbers );

			return "پیام به این شماره (ها) ارسال نشد: " . $failed_numbers_list;
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
		$body          = wp_remote_retrieve_body( $response );
		$json_response = json_decode( $body, true );

		// Check if 'msg' is 'success'
		if ( ! isset( $json_response['msg'] ) || $json_response['msg'] !== 'success' ) {
			// If msg is not 'success', output the error message
			return 'خطا : ' . $json_response['msg'];
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
