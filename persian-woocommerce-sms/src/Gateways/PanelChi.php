<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\Gateways\Features\SendPatternFeature;
use PW\PWSMS\Helpers\Curl;

class PanelChi extends Gateway implements SendPatternFeature {

	public string $api_url = 'https://api.panelchi.com/sms';

	public string $api_key;

	public static function id(): string {
		return 'panelchi';
	}

	public static function name(): string {
		return 'PanelChi.com - پنل چی';
	}

	public function send(): bool {
		$this->api_key = $this->get_token();

		if ( empty( $this->api_key ) ) {
			throw new Exception( 'کلید API را در بخش تنظیمات وب‌سرویس تعریف کنید.' );
		}

		if ( ! str_starts_with( $this->api_key, 'Bearer' ) ) {
			$this->api_key = 'Bearer ' . $this->api_key;
		}

		if ( $this->is_pattern() ) {
			return $this->send_pattern_sms();
		}

		return $this->send_normal_sms();
	}

	/**
	 * @throws Exception
	 */
	public function send_pattern_sms(): bool {

		$pattern = $this->parse_pattern();

		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: ' . $this->api_key,
		];

		$data = [
			'sourceNumber' => $this->senderNumber,
			'pattern'      => $pattern['code'],
			'variables'    => $pattern['vars'],
		];

		foreach ( $this->mobile as $recipient ) {

			$data['recipient'] = $recipient;

			try {
				$response = Curl::post( $this->api_url . '/pattern', wp_json_encode( $data ), $headers );
			} catch ( Exception $e ) {
				$this->failed_numbers[ $recipient ] = $e->getMessage();
				continue;
			}

			if ( isset( $response['data']['uid'] ) ) {
				continue;
			}

			if ( isset( $response['detail'] ) ) {
				$this->failed_numbers[ $recipient ] = $response['detail'];
				continue;
			}

			if ( isset( $response['title'] ) ) {
				$this->failed_numbers[ $recipient ] = $response['title'];
				continue;
			}

			$this->failed_numbers[ $recipient ] = 'خطای ناشناخته در ارسال به پنل‌چی رخ داده است.';
		}

		return $this->format_failed_numbers();
	}

	/**
	 * @throws Exception
	 */
	public function send_normal_sms(): bool {

		$data = [
			'sourceNumber' => $this->senderNumber,
			'recipients'   => $this->mobile,
			'message'      => $this->message,
		];

		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: ' . $this->api_key,
		];

		$response = Curl::post( $this->api_url . '/send', wp_json_encode( $data ), $headers );

		if ( isset( $response['data']['uid'] ) ) {
			return true;
		}

		if ( isset( $response['detail'] ) ) {
			throw new Exception( $response['detail'], $response['code'] ?? 0 );
		}

		if ( isset( $response['title'] ) ) {
			throw new Exception( $response['title'], $response['code'] ?? 0 );
		}

		throw new Exception( 'خطای ناشناخته در ارسال به پنل‌چی رخ داده است.' );
	}

}
