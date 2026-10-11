<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\Helpers\Curl;

class SignalAds extends Gateway {

	public string $api_url = 'https://transmitor.signalads.com/api_v1';

	public string $api_key;

	public static function id(): string {
		return 'signalads';
	}

	public static function name(): string {
		return 'Panel.SignalAds.com - سیگنال ادز';
	}

	public function send() {
		$this->api_key = $this->get_token();

		if ( empty( $this->api_key ) ) {
			throw new Exception( 'کلید وبسرویس را در بخش تنظیمات وبسرویس تعریف کنید.' );
		}

		if ( ! str_starts_with( $this->api_key, 'Bearer' ) ) {
			$this->api_key = 'Bearer ' . $this->api_key;
		}

		if ( empty( $this->senderNumber ) ) {
			throw new Exception( 'لطفا شماره فرستنده را در تنظیمات وبسرویس ثبت کنید.' );
		}

		if ( ! $this->is_pattern() ) {
			return $this->send_normal_sms();
		}

		$this->send_pattern_sms();

		return $this->format_failed_numbers();
	}

	/**
	 * @throws Exception
	 */
	public function send_normal_sms() {

		$headers = [
			'Authorization: ' . $this->api_key,
			'Accept: application/json',
			'Content-Type: application/json; charset=utf-8',
		];

		$data = [
			'from'    => $this->senderNumber,
			'message' => $this->ensure_cancel_suffix( $this->message ),
			'numbers' => $this->mobile,
		];

		try {
			$response = Curl::post( $this->api_url . '/sms/send', wp_json_encode( $data ), $headers );
		} catch ( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}

		if ( ! empty( $response['success'] ) && $response['success'] === true ) {
			return true;
		}

		if ( is_array( $response['detail'] ?? '' ) ) {
			$messages = array_column( $response['detail'] ?? [], 'msg' );
			$error    = implode( ', ', $messages );
		} else {
			$error = $response['detail'] ?? $response['message'] ?? $response[0]['message'] ?? $response['error'] ?? 'خطای ناشناخته‌ای در ارسال پیامک توسط سیگنال ادز رخ داده است.';
		}

		throw new Exception( $error );
	}

	public function send_pattern_sms() {
		$pattern = $this->parse_pattern();
		$headers = [
			'Authorization: ' . $this->api_key,
			'Accept: application/json',
			'Content-Type: application/json; charset=utf-8',
		];

		foreach ( $this->mobile as $recipient ) {

			$data = [
				'from'       => $this->senderNumber,
				'pattern_id' => intval( $pattern['code'] ),
				'number'     => $recipient,
				'parameters' => $pattern['vars'],
			];

			try {
				$response = Curl::post( $this->api_url . '/patterns/send', wp_json_encode( $data ), $headers );
			} catch ( Exception $e ) {
				$this->failed_numbers[ $recipient ] = $e->getMessage();
				continue;
			}

			if ( ! empty( $response['success'] ) && $response['success'] === true ) {
				continue;
			}

			if ( is_array( $response['detail'] ?? '' ) ) {
				$messages = array_column( $response['detail'] ?? [], 'msg' );
				$error    = implode( ', ', $messages );
			} else {
				$error = $response['detail'] ?? $response['message'] ?? $response[0]['message'] ?? $response['error'] ?? 'خطای ناشناخته‌ای در ارسال پیامک الگو توسط سیگنال ادز رخ داده است.';
			}

			$this->failed_numbers[ $recipient ] = $error;
		}
	}

	protected function ensure_cancel_suffix( string $message ): string {
		$message = rtrim( $message );

		if ( $message === '' ) {
			return $message;
		}

		$existing = $message;

		// Persian
		$existing = str_ireplace(
			[ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ],
			[ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ],
			$existing
		);

		// Arabic
		$existing = str_ireplace(
			[ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ],
			[ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ],
			$existing
		);

		if ( preg_match( '/لغو\s*[0-9]{1,3}\s*$/u', $existing ) ) {

			return $message;
		}

		return $message . ' لغو ۱۱';
	}
}
