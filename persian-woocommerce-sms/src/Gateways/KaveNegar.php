<?php

namespace PW\PWSMS\Gateways;

class KaveNegar extends Gateway {

	public string $api_key;

	public static function id(): string {
		return 'kavenegar';
	}

	public static function name(): string {
		return 'KaveNegar.com - کاوه نگار';
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

	private function send_normal_sms() {
		$recipients = implode( ',', $this->mobile );

		$query_params = [
			'sender'   => $this->senderNumber,
			'receptor' => $recipients,
			'message'  => $this->message,
		];

		$url = "https://api.kavenegar.com/v1/{$this->api_key}/sms/send.json?" . http_build_query( $query_params );

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return 'خطا در برقراری ارتباط با سرور: ' . $response->get_error_message();
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return 'پاسخی از سرور دریافت نشد.';
		}

		$json = json_decode( $body );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return 'خطا در پردازش پاسخ سرور: ' . json_last_error_msg();
		}

		if ( ! empty( $json->return->status ) && $json->return->status == 200 ) {
			return true;
		}

		return 'ارسال پیام با خطا مواجه شد: ' . ( $json->return->message ?? 'پاسخ نامشخص از سرور' );
	}

	private function send_pattern_sms() {
		$pattern = $this->parse_pattern();

		$recipients = implode( ',', $this->mobile );

		$token_params = '';

		foreach ( $pattern['vars'] as $key => $value ) {

			$value        = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
			$token_params .= '&' . $key . '=' . rawurlencode( $value );

		}

		$url = sprintf(
			"https://api.kavenegar.com/v1/%s/verify/lookup.json?receptor=%s&template=%s%s",
			$this->api_key,
			$recipients,
			rawurlencode( $pattern['code'] ),
			$token_params
		);

		$remote = wp_remote_get( $url );

		if ( is_wp_error( $remote ) ) {
			return 'خطا در ارتباط با سرور: ' . $remote->get_error_message();
		}

		$sms_response = wp_remote_retrieve_body( $remote );

		if ( empty( $sms_response ) ) {
			return 'پاسخی از سرور دریافت نشد.';
		}

		$json_response = json_decode( $sms_response );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return 'خطا در پردازش پاسخ سرور: ' . json_last_error_msg();
		}

		if ( ! empty( $json_response->return->status ) && $json_response->return->status == 200 ) {
			return true;
		}

		return $json_response->return->message ?? $sms_response;
	}

	public function is_pattern(): bool {

		if ( parent::is_pattern() ) {
			return true;
		}

		return $this->is_legacy_pattern();
	}

	private function is_legacy_pattern(): bool {
		return str_contains( $this->message, 'template=' );
	}

	public function parse_pattern(): array {

		if ( $this->is_legacy_pattern() ) {
			return $this->parse_legacy_pattern();
		}

		return parent::parse_pattern();
	}

	public function parse_legacy_pattern(): array {

		$result = [
			'code' => '',
			'vars' => [],
		];

		$message = str_replace( [ "\r\n", "\n", "\\r\\n", "\\n", "|" ], '', $this->message );

		$message = str_replace( 'template=', '|template=', $message );

		foreach ( range( 0, 20 ) as $index ) {
			$token   = $index === 0 ? "token=" : "token{$index}=";
			$message = str_replace( $token, "|$token", $message );
		}

		$parts = array_filter( array_map( 'trim', explode( '|', $message ) ) );

		foreach ( $parts as $part ) {

			if ( ! str_contains( $part, '=' ) ) {
				continue;
			}

			[ $key, $value ] = explode( '=', $part, 2 );

			$key   = strtolower( trim( $key ) );
			$value = trim( $value );

			if ( $key === 'template' ) {
				$result['code'] = $value;
				continue;
			}

			if ( str_starts_with( $key, 'token' ) ) {
				$result['vars'][ $key ] = $value;
			}

		}

		return $result;
	}
}
