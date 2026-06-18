<?php

namespace PW\PWSMS\Gateways;


use Exception;
use PW\PWSMS\Gateways\Features\SendPatternFeature;
use PW\PWSMS\Helpers\Curl;

class SMSIRToken extends Gateway implements SendPatternFeature {

	public string $api_url = 'https://api.sms.ir/v1/';

	public string $api_key;

	public static function id(): string {
		return 'smsir-new';
	}

	public static function name(): string {
		return 'SMS.ir (کلید دسترسی)';
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
	public function send_pattern_sms(): bool {

		$pattern = $this->parse_pattern();

		$parameters = [];

		foreach ( $pattern['vars'] as $name => $value ) {
			$parameters[] = [
				"name"  => $name,
				"value" => $value,
			];
		}

		$headers = [
			'Content-Type: application/json',
			'x-api-key: ' . $this->api_key,
		];

		$data = [
			'templateId' => $pattern['code'],
			'parameters' => $parameters,
		];

		foreach ( $this->mobile as $recipient ) {

			$data['mobile'] = $recipient;

			try {
				$response = Curl::post( $this->api_url . 'send/verify', wp_json_encode( $data ), $headers );
			} catch ( Exception $e ) {
				$this->failed_numbers[ $recipient ] = $e->getMessage();
				continue;
			}

			if ( isset( $response['messageId'] ) ) {
				continue;
			}

			if ( isset( $response['message'] ) ) {
				$this->failed_numbers[ $recipient ] = $response['message'];
				continue;
			}

			if ( isset( $response['title'] ) ) {
				$this->failed_numbers[ $recipient ] = $response['title'];
				continue;
			}

			$this->failed_numbers[ $recipient ] = 'خطای ناشناخته در ارسال به sms.ir رخ داده است.';
		}

		return $this->format_failed_numbers();
	}

	/**
	 * @throws Exception
	 */
	public function send_normal_sms(): bool {

		$data = [
			'lineNumber'   => $this->senderNumber,
			'messageText'  => $this->message,
			'mobiles'      => $this->mobile,
			'sendDateTime' => null,
		];

		$headers = [
			'Content-Type: application/json',
			'X-API-KEY: ' . $this->api_key,
		];

		$response = Curl::post( $this->api_url . 'send/bulk', wp_json_encode( $data ), $headers );

		if ( isset( $response['data']['packId'] ) ) {
			return true;
		}

		if ( isset( $response['message'] ) ) {
			throw new Exception( $response['message'], $response['status'] ?? 0 );
		}

		if ( isset( $response['title'] ) ) {
			throw new Exception( $response['title'], $response['status'] ?? 0 );
		}

		throw new Exception( 'خطای ناشناخته در ارسال به sms.ir رخ داده است.' );
	}

}
