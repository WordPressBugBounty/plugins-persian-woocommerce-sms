<?php

namespace PW\PWSMS\Gateways;


class Mediana extends Gateway {

	public string $api_url = 'https://api.mediana.ir/sms/v1/send';
	public string $api_key;
	public array $headers;


	public static function id(): string {
		return 'mediana';
	}

	public static function name(): string {
		return 'Mediana.ir - مدیانا';
	}

	public function send() {

		$this->api_key = $this->get_token();

		if ( empty( $this->api_key ) ) {
			return 'لطفا کلید API اتصال به وبسرویس را به درستی ثبت نمایید.';
		}

		if ( ! str_starts_with( $this->api_key, 'Bearer' ) ) {
			$this->api_key = 'Bearer ' . $this->api_key;
		}

		$this->headers = [
			'Content-Type'  => 'application/json',
			'Accept'        => '*/*',
			'Authorization' => $this->api_key,
		];

		if ( $this->is_pattern() ) {
			return $this->send_pattern_sms();
		}

		return $this->send_normal_sms();

	}

	private function send_pattern_sms() {

		$pattern = $this->parse_pattern();

		$payload = [
			'patternCode' => $pattern['code'],
			'recipients'  => $this->mobile,
			'parameters'  => $pattern['vars'],
		];

		$remote = wp_remote_post( $this->api_url . '/pattern', [
			'headers' => $this->headers,
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

		if ( ! empty( json_last_error() ) || ! is_array( $response_data ) ) {
			return 'قالب پاسخ دریافتی از وب‌سرویس نامعتبر است.';
		}

		if ( empty( $response_data['data']['succeed'] ) ) {
			return 'خطای ارسال پیامک از سمت وب سرویس.';
		}

		return true;
	}

	private function send_normal_sms( $informational = false ) {

		$payload = [
			'recipients'  => $this->mobile,
			'messageText' => $this->message,
		];

		if ( $informational ) {
			$payload['type'] = 'Informational';
		} else {
			$payload['sendingNumber'] = $this->senderNumber;
		}

		$remote = wp_remote_post( $this->api_url . '/sms', [
			'headers' => $this->headers,
			'body'    => json_encode( $payload ),
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

		if ( ! empty( json_last_error() ) || ! is_array( $response_data ) ) {
			return 'قالب پاسخ دریافتی از وب‌سرویس نامعتبر است.';
		}

		// Resend without from number (informational message) if sender doesn't exists!
		if ( isset( $response_data['meta']['errors'][0]['errorCode'] ) && $response_data['meta']['errors'][0]['errorCode'] == "1101" ) {
			return self::send_normal_sms( true );
		}

		if ( isset( $result['data']['succeed'] ) && $result['data']['succeed'] == "1" ) {
			return true;
		} elseif ( isset( $result['meta']['errorMessage'] ) && ! empty( $result['meta']['errorMessage'] ) ) {
			return $result['meta']['errorMessage'];
		} elseif ( isset( $result['meta']['errors'] ) && ! empty( $result['meta']['errors'] ) ) {
			return $result['meta']['errorCode'];
		}

		if ( empty( $response_data['data']['succeed'] ) ) {
			return 'خطای ارسال پیامک از سمت وب سرویس.';
		}

		return true;
	}
}
