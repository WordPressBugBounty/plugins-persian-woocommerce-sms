<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\Gateways\Features\SendPatternFeature;
use PW\PWSMS\Helpers\Curl;

class KaveNegar extends Gateway implements SendPatternFeature {

	public string $api_key;

	public static function id(): string {
		return 'kavenegar';
	}

	public static function name(): string {
		return 'KaveNegar.com - کاوه نگار';
	}

	public function send(): bool {
		$this->api_key = $this->get_token();

		if ( empty( $this->api_key ) ) {
			throw new Exception( 'کلید وبسرویس را در بخش تنظیمات وبسرویس تعریف کنید.' );
		}

		if ( $this->is_pattern() ) {
			return $this->send_pattern_sms();
		}

		return $this->send_normal_sms();
	}

	/**
	 * @throws Exception
	 */
	public function send_normal_sms(): bool {
		$recipients = implode( ',', $this->mobile );

		$query_params = [
			'sender'   => $this->senderNumber,
			'receptor' => $recipients,
			'message'  => $this->message,
		];

		$url = "https://api.kavenegar.com/v1/{$this->api_key}/sms/send.json?" . http_build_query( $query_params );

		$response = Curl::post( $url );
		$status   = $response['return']['status'] ?? 0;

		if ( $status == 200 ) {
			return true;
		}

		if ( isset( $response['return']['message'] ) ) {
			throw new Exception( $response['return']['message'], $status );
		}

		throw new Exception( 'خطای ناشناخته در ارسال به کاوه‌نگار رخ داده است.' );
	}

	/**
	 * @throws Exception
	 */
	public function send_pattern_sms(): bool {
		$pattern = $this->parse_pattern();

		$token_params = '';

		foreach ( $pattern['vars'] as $key => $value ) {

			$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );

			// Kavenegar doesn't support Space and ZWNJ in regular tokens
			if ( ! in_array( $key, [ 'token10', 'token20' ] ) ) {
				$value = str_replace( [ ' ', "\xE2\x80\x8C" ], '-', $value );
			}

			$token_params .= '&' . $key . '=' . rawurlencode( $value );

		}

		foreach ( $this->mobile as $recipient ) {

			$url = sprintf(
				"https://api.kavenegar.com/v1/%s/verify/lookup.json?receptor=%s&template=%s%s",
				$this->api_key,
				$recipient,
				rawurlencode( $pattern['code'] ),
				$token_params
			);

			try {
				$response = Curl::post( $url );
			} catch ( Exception $e ) {
				$this->failed_numbers[ $recipient ] = $e->getMessage();
				continue;
			}

			$status = $response['return']['status'] ?? 0;

			if ( $status == 200 ) {
				continue;
			}

			if ( isset( $response['return']['message'] ) ) {
				$this->failed_numbers[ $recipient ] = $response['return']['message'];
				continue;
			}

			$this->failed_numbers[ $recipient ] = 'خطای ناشناخته در ارسال به کاوه‌نگار رخ داده است.';
		}

		return $this->format_failed_numbers();
	}

	public function is_pattern(): bool {

		if ( parent::is_pattern() ) {
			return true;
		}

		return $this->is_legacy_pattern();
	}

	public function is_legacy_pattern(): bool {
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
