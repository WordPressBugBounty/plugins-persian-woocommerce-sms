<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\Helpers\Curl;

class WebOne extends Gateway {

	public static function id(): string {
		return 'webone';
	}

	public static function name(): string {
		return 'WebOne-SMS.com - وب وان';
	}

	public function send() {

		if ( empty( $this->username ) || empty( $this->password ) ) {
			throw new Exception( 'لطفا نام کاربری و رمز عبور وبسرویس وب وان را در بخش تنظیمات وبسرویس ثبت نمایید.' );
		}

		if ( empty ( $this->senderNumber ) ) {
			throw new Exception( 'لطفا شماره ارسال کننده وبسرویس وب وان را در بخش تنظیمات وبسرویس ثبت نمایید.' );
		}

		$headers = [
			"Content-Type: application/json",
		];

		$data = [
			'UserName' => $this->username,
			'Password' => $this->password,
			'From'     => $this->senderNumber,
			'Message'  => $this->message
		];

		foreach ( $this->mobile as $recipient ) {

			$data['To'] = $recipient;

			try {

				$response = Curl::post( 'https://rest.payamakapi.ir/api/v1/SMS/Send', wp_json_encode( $data ), $headers );

			} catch ( Exception $e ) {

				$this->failed_numbers[ $recipient ] = $e->getMessage();
				continue;

			}

			if ( ( $response['Status'] ?? false ) === true ) {
				continue;
			}

			$this->failed_numbers[ $recipient ] = $response['Message'] ?? 'خطای ناشناخته ای در ارسال پیامک رخ داده است.';
		}

		return $this->format_failed_numbers();
	}

}
