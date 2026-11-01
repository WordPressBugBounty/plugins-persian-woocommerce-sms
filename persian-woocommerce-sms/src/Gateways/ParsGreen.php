<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\Helpers\Curl;

class ParsGreen extends Gateway {

	public static function id(): string {
		return 'parsgreen';
	}

	public static function name(): string {
		return 'ParsGreen.com - پارس گرین';
	}

	/**
	 * @throws Exception
	 */
	public function send(): bool {
		$api_key = $this->get_token();

		if ( empty( $api_key ) ) {
			throw new Exception( 'کلید وبسرویس را در بخش تنظیمات وبسرویس تعریف کنید.' );
		}

		if ( ! str_starts_with( $api_key, 'basic apikey:' ) ) {
			$api_key = 'basic apikey:' . $api_key;
		}

		$response = Curl::post(
			'https://sms.parsgreen.ir/Apiv2/Message/SendSms',
			wp_json_encode( [
				'SmsBody'   => $this->message,
				'Mobiles'   => $this->mobile,
				'SmsNumber' => $this->senderNumber,
			] ),
			[
				'Authorization: ' . $api_key,
				'Accept: application/json',
				'Content-Type: application/json; charset=utf-8',
			]
		);

		if ( $response['R_Success'] ) {
			return true;
		}

		throw new Exception( $response['R_Message'] ?? $response['R_Error'] ?? 'خطای ناشناخته‌ای در ارسال پیامک توسط پارس گرین رخ داده است.' );
	}

}
