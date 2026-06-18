<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\Helpers\Curl;


class MeliPayamak extends Gateway {

	public string $api_url = 'https://rest.payamak-panel.com/api/SendSMS';

	public static function id(): string {
		return 'melipayamak_unified';
	}

	public static function name(): string {
		return 'melipayamak.com - ملی پیامک';
	}

	public function send(): bool {

		if ( empty( $this->username ) || empty( $this->password ) ) {
			throw new Exception( 'لطفا نام کاربری و کلید دسترسی ملی پیامک را در بخش وبسرویس ثبت نمایید.' );
		}

		$headers = [
			"Content-Type: application/json; charset=utf-8",
		];

		if ( $this->is_pattern() ) {

			$pattern = $this->parse_pattern();

			$url = $this->api_url . '/BaseServiceNumber';

			$data = [
				'username' => $this->username,
				'password' => $this->password,
				'text'     => implode( ';', $pattern['vars'] ),
				'bodyId'   => intval( $pattern['code'] ),
			];

		} else {

			$url = $this->api_url . '/SendSMS';

			$data = [
				'username' => $this->username,
				'password' => $this->password,
				'from'     => $this->senderNumber,
				'text'     => $this->message,
				'isflash'  => false,
			];

		}

		foreach ( $this->mobile as $recipient ) {

			$data['to'] = $recipient;

			try {
				$response = Curl::post( $url, wp_json_encode( $data ), $headers );
			} catch ( Exception $e ) {
				$this->failed_numbers[ $recipient ] = $e->getMessage();
				continue;
			}

			$value = $response['Value'] ?? '';

			if ( strlen( $value ) > 15 ) {
				continue;
			}

			if ( $value ) {
				$this->failed_numbers[ $recipient ] = sprintf( 'خطای %s در ارسال پیامک رخ داده است.', $value );
				continue;
			}

			if ( isset( $response['StrRetStatus'] ) ) {
				$this->failed_numbers[ $recipient ] = sprintf( 'خطای %s در ارسال پیامک رخ داده است.', $response['StrRetStatus'] );
				continue;
			}

			$this->failed_numbers[ $recipient ] = 'خطای ناشناخته در ارسال به ملی پیامک رخ داده است.';
		}

		return $this->format_failed_numbers();
	}

	/**
	 * @throws Exception
	 */
	public function get_credit(): int {

		$data = [
			'username' => $this->username,
			'password' => $this->password,
		];

		$response = Curl::post( $this->api_url . '/GetCredit', wp_json_encode( $data ), [
			"Content-Type: application/json; charset=utf-8",
		] );

		$credit = $response['Value'] ?? 0;

		return is_numeric( $credit ) ? intval( $credit ) : 0;
	}

	/**
	 * @throws Exception
	 */
	public function send_pattern_sms(): bool {
		return $this->send();
	}

	public function is_pattern(): bool {
		if ( parent::is_pattern() ) {
			return true;
		}

		return $this->is_legacy_pattern();
	}

	// @PATTERN_CODE@{var1};{var2};{var3}##shared
	public function is_legacy_pattern(): bool {
		return str_starts_with( $this->message, '@' ) && str_contains( $this->message, '##' );
	}

	public function parse_pattern(): array {
		if ( $this->is_legacy_pattern() ) {
			return $this->parse_legacy_pattern();
		}

		return parent::parse_pattern();
	}

	public function parse_legacy_pattern(): array {

		$parts = explode( '@', $this->message );
		$code  = $parts[1] ?? '';
		$vars  = str_replace( [ '##shared', '##' ], '', $parts[2] ?? '' );

		return [
			'code' => trim( $code ),
			'vars' => array_values( array_filter( array_map( 'trim', explode( ';', $vars ) ) ) ),
		];
	}
}