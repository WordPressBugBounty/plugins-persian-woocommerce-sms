<?php

namespace PW\PWSMS\Gateways;

use DateTime;
use DateTimeZone;
use PW\PWSMS\PWSMS;

class Ghasedak implements GatewayInterface {
	use GatewayTrait;

	public string $api_url = 'https://gateway.ghasedaksms.com/api/v1';

	public static function id() {
		return 'ghasedak';
	}

	public static function name() {
		return 'ghasedak.me';
	}

	public function send() {
		$recipients      = $this->mobile;
		$api_key         = ! empty( $this->username ) ? trim( $this->username ) : trim( $this->password );
		$from            = trim( $this->senderNumber );
		$message_content = $this->message;

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
		$pattern_api_url = $this->api_url . '/Send/NewOTP';

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
			'ApiKey'       => $api_key,
			'agent: WooCommerce',
		];

		$recipients = implode( ',', $recipients );

		$data = [
			'template' => trim( $pattern_code ),
			'type'     => '1',
			'receptor' => $recipients,
			'allparam' => [],
		];

		$all_param = [];
		foreach ( $pattern_data as $param => $value ) {
			$all_param[] = [
				'param' => $param,
				'value' => $value
			];
		}

		$data['allparam'] = $all_param;

		$remote = wp_remote_post( $pattern_api_url, [
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

		if ( isset( $response_data['isSuccess'] ) && $response_data['isSuccess'] ) {
			return true;
		}

		return 'خطای وبسرویس: ' . $response_data['message'] ?? 'خطای نامشخص';
	}


	private function send_simple_sms( array $recipients, string $from, string $message_content, string $api_key ) {
		$date_time           = new DateTime();
		$date_string         = $date_time->format( 'c' );
		$client_reference_id = wp_rand( 1, 1000000 );

		$single_api_url = $this->api_url . '/Send/Bulk';

		// Check for required fields
		if ( empty( $api_key ) || empty( $message_content ) || empty( $recipients ) ) {
			return 'اطلاعات پنل، یا پیامک به درستی وارد نشده.';
		}

		$recipients = implode( ',', $recipients );

		$data = [
			'sendDate' => $date_string,
			'Sender'   => $from,
			'receptor' => $recipients,
			'message'  => $message_content,
			'checkId'  => $client_reference_id . ""
		];

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'ApiKey'       => $api_key,
			'agent: WooCommerce',
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

		if ( isset( $response_data['isSuccess'] ) && $response_data['isSuccess'] ) {
			return true;
		}

		return 'خطای وبسرویس: ' . $response_data['message'] ?? 'خطای نامشخص';
	}
}
