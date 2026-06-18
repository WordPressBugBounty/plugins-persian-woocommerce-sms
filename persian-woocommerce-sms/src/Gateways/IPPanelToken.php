<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\Gateways\Features\SendPatternFeature;
use PW\PWSMS\Helpers\Curl;

class IPPanelToken extends Gateway implements SendPatternFeature {

	/**
	 * @var string
	 */
	public string $api_url = 'https://edge.ippanel.com/v1/';

	/**
	 * @var string
	 */
	private string $api_key = '';

	public static function id(): string {
		return 'ippanel-token';
	}

	public static function name(): string {
		return 'ippanel.com (کلید دسترسی)';
	}

	/**
	 * @return bool|string|null
	 *
	 * @throws Exception
	 */
	public function send(): bool {
		$this->api_key = $this->get_token();

		if ( empty( $this->api_key ) ) {
			throw new Exception( 'کلید وبسرویس را در بخش تنظیمات وبسرویس تعریف کنید.' );
		}

		if ( empty( $this->senderNumber ) ) {
			throw new Exception( 'شماره فرستنده پیامک تعیین نشده است.' );
		}

		if ( $this->is_pattern() ) {
			return $this->send_pattern_sms();
		}

		return $this->send_normal_sms();
	}

	/**
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function send_pattern_sms(): bool {

		$pattern = $this->parse_pattern();

		$data = [
			'sending_type' => 'pattern',
			'from_number'  => $this->senderNumber,
			'code'         => $pattern['code'],
			'params'       => $pattern['vars'],
		];

		$headers = [
			'Content-Type: application/json',
			'Authorization: ' . $this->api_key,
		];

		foreach ( $this->mobile as $recipient ) {

			$data['recipients'] = [ $recipient ];

			try {
				$response = Curl::post( $this->api_url . 'api/send', wp_json_encode( $data ), $headers );
			} catch ( Exception $e ) {
				$this->failed_numbers[ $recipient ] = $e->getMessage();
				continue;
			}

			if ( isset( $response['meta']['status'] ) && $response['meta']['status'] ) {
				continue;
			}

			if ( isset( $response['meta']['message'] ) ) {
				$this->failed_numbers[ $recipient ] = $response['meta']['message'];
				continue;
			}

			$this->failed_numbers[ $recipient ] = 'خطای ناشناخته در ارسال به درگاه پیامک رخ داده است.';
		}

		return $this->format_failed_numbers();
	}

	/**
	 * @throws Exception
	 */
	public function send_normal_sms(): bool {

		$data = [
			'sending_type' => 'webservice',
			'from_number'  => $this->senderNumber,
			'message'      => $this->message,
			'params'       => [
				'recipients' => $this->mobile,
			],
		];

		$headers = [
			'Content-Type: application/json',
			'Authorization: ' . $this->api_key,
		];

		$response = Curl::post( $this->api_url . 'api/send', wp_json_encode( $data ), $headers );

		if ( isset( $response['meta']['status'] ) && $response['meta']['status'] ) {
			return true;
		}

		if ( isset( $response['meta']['message'] ) ) {
			throw new Exception( $response['meta']['message'] );
		}

		throw new Exception( 'خطای ناشناخته در ارسال به درگاه پیامک رخ داده است.' );
	}

}
