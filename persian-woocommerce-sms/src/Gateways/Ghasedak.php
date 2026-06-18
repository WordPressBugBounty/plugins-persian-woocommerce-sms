<?php

namespace PW\PWSMS\Gateways;

use DateTime;

class Ghasedak extends Gateway {

	public string $api_url = 'https://gateway.ghasedaksms.com/api/v1';
	public string $api_key;

	public static function id(): string {
		return 'ghasedak';
	}

	public static function name(): string {
		return 'Ghasedak.me - قاصدک';
	}

	public function send() {
		$this->api_key = $this->get_token();

		if ( empty( $this->api_key ) ) {
			return 'کلید وبسرویس را در بخش تنظیمات وبسرویس تعریف کنید.';
		}

		if ( $this->is_pattern() ) {
			return $this->send_pattern_sms();
		}

		return $this->send_normal_sms();
	}

	public function send_pattern_sms() {
		$pattern = $this->parse_pattern();

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'ApiKey'       => $this->api_key,
			'agent: WooCommerce',
		];

		$recipients = implode( ',', $this->mobile );

		$data = [
			'template' => $pattern['code'],
			'type'     => '1',
			'receptor' => $recipients,
		];

		$all_param = [];

		foreach ( $pattern['vars'] as $param => $value ) {

			$all_param[] = [
				'param' => $param,
				'value' => $value,
			];

		}

		$data['allparam'] = $all_param;

		$remote = wp_remote_post( $this->api_url . '/Send/NewOTP', [
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
			return 'پاسخی از وب‌سرویس دریافت نشد.';
		}

		$response_data = json_decode( $response, true );
		if ( ! empty( json_last_error() ) ) {
			return 'قالب پاسخ دریافتی از وب‌سرویس نامعتبر است.';
		}

		if ( isset( $response_data['isSuccess'] ) && $response_data['isSuccess'] ) {
			return true;
		}

		return 'خطای وبسرویس: ' . $response_data['message'] ?? 'خطایی ناشناخته رخ داده است.';
	}

	public function send_normal_sms() {
		$date_time           = new DateTime();
		$date_string         = $date_time->format( 'c' );
		$client_reference_id = wp_rand( 1, 1000000 );

		$recipients = implode( ',', $this->mobile );

		$data = [
			'sendDate' => $date_string,
			'Sender'   => $this->senderNumber,
			'receptor' => $recipients,
			'message'  => $this->message,
			'checkId'  => $client_reference_id . "",
		];

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'ApiKey'       => $this->api_key,
			'agent: WooCommerce',
		];

		$remote = wp_remote_post( $this->api_url . '/Send/Bulk', [
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
			return 'پاسخی از وب‌سرویس دریافت نشد.';
		}

		$response_data = json_decode( $response, true );

		if ( ! empty( json_last_error() ) ) {
			return 'قالب پاسخ دریافتی از وب‌سرویس نامعتبر است.';
		}

		if ( isset( $response_data['isSuccess'] ) && $response_data['isSuccess'] ) {
			return true;
		}

		return 'خطای وبسرویس: ' . $response_data['message'] ?? 'خطایی ناشناخته رخ داده است.';
	}
}
