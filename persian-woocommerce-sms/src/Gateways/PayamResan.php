<?php

namespace PW\PWSMS\Gateways;

use DateTime;
use DateTimeZone;
use PW\PWSMS\PWSMS;

class PayamResan implements GatewayInterface {
	use GatewayTrait;

	public string $api_url = 'https://api.sms-webservice.com/api/V3';

	public static function id() {
		return 'payamresan';
	}

	public static function name() {
		return 'payam-resan.com';
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
		$pattern_api_url     = $this->api_url . '/SendTokenMulti';
		$client_reference_id = rand( 1, 1000000 );

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
		];

		$data = [
			'ApiKey'      => $api_key,
			'TemplateKey' => trim( $pattern_code ),
			'Recipients'  => [],
		];


		foreach ( $recipients as $recipient ) {
			$data['Recipients'][] = [
				'Destination' => $recipient,
				'UserTraceId' => $client_reference_id,
				'Parameters'  => array_values( $pattern_data )
			];

		}

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

		if ( isset( $response_data['Success'] ) && $response_data['Success'] ) {
			return true;
		}


		return 'خطای وبسرویس: ' . $response_data['Error'] ?? 'خطای نامشخص';
	}

	private function send_simple_sms( array $recipients, string $from, string $message_content, string $api_key ) {
		$single_api_url = $this->api_url . '/SendBulk';

		// Check for required fields
		if ( empty( $api_key ) || empty( $message_content ) || empty( $recipients ) ) {
			return 'اطلاعات پنل، یا پیامک به درستی وارد نشده.';
		}

		$data = [
			'ApiKey'     => $api_key,
			'Text'       => $message_content,
			'Sender'     => $from,
			'Recipients' => $recipients
		];

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
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

		if ( isset( $response_data['Success'] ) && $response_data['Success'] ) {
			return true;
		}

		return 'خطای وبسرویس: ' . $response_data['Error'] ?? 'خطای نامشخص';
	}
}
