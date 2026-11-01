<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\Helpers\Curl;


class OneSMS extends Gateway {

	public string $api_url = 'https://connect.onesms.ir/v1';

	public static function id(): string {
		return 'onesms';
	}

	public static function name(): string {
		return 'onesms.ir - وان اس‌ام‌اس';
	}

	public function send(): bool {

		$api_key = $this->get_token();

		if ( empty( $api_key ) ) {
			throw new Exception( 'لطفا کلید دسترسی وان اس‌ام‌اس را در تنظیمات وبسرویس ثبت نمایید.' );
		}

		if ( empty( $this->senderNumber ) ) {
			throw new Exception( 'لطفا شماره ارسال کننده وان اس‌ام‌اس را در تنظیمات وبسرویس ثبت نمایید.' );
		}

		$headers = [
			"Content-Type: application/json",
			'X-API-Key: ' . $api_key,
		];

		if ( $this->is_pattern() ) {

			$pattern = $this->parse_pattern();

			$url = $this->api_url . '/send/pattern';

			$data = [
				'pattern' => $pattern['code'],
				'data'    => $pattern['vars'],
			];

		} else {

			$url  = $this->api_url . '/send/quick';
			$data = [ 'body' => $this->message, ];

		}

		$data['from'] = $this->senderNumber;
		$data['to']   = $this->mobile;

		$response = Curl::post( $url, wp_json_encode( $data ), $headers );

		if ( ( $response['code'] ?? '' ) == '0' ) {
			return true;
		}

		throw new Exception ( $response['message'] ?? 'خطای ناشناخته ای هنگام ارسال پیامک رخ داده است.' );
	}
}