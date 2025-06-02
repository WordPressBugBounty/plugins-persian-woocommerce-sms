<?php


namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class NMTSMS implements GatewayInterface {
	use GatewayTrait;

	public static function id() {
		return 'nmtsms';
	}

	public static function name() {
		return 'nmtsms.ir (خدماتی)';
	}


	public function send() {
		$response = false;
		$username = ! empty( $this->username ) ? trim( $this->username ) : trim( $this->password );
		$from     = $this->senderNumber;
		$to       = $this->mobile;
		$massage  = $this->message;

		if ( empty( $username ) ) {
			return 'شناسه کاربری خود را در تنظیمات وبسرویس پیامک حرفه ای وارد نمایید.';
		}

		$body = [
			"Token"        => $username,
			"Message"      => $massage,
			"Mobiles"      => $to,
			"SenderNumber" => $from,
		];


		try {

			$remote = wp_remote_post( "https://nmtsms.ir/api/v1/SendBulk", [
				'body'    => json_encode( $body ),
				'headers' => [
					"Content-Type" => "application/json; charset=utf-8",
					"Accept"       => "application/json"
				],
			] );

			$response = wp_remote_retrieve_body( $remote );

			if ( empty( $response ) || is_wp_error($response)  ) {
				throw new Exception( "اتصال به وبسرویس برقرار نیست. لطفاً دوباره تلاش کنید." );
			}

			$response = json_decode( $response );

			if ( ! empty( json_last_error() ) ) {
				throw new Exception( "خطا در پردازش داده‌ها. لطفاً دوباره تلاش کنید." );
			}

		} catch ( Exception $exception ) {
			$response = "خطا: " . $exception->getMessage();
		}

		if ( isset( $response->Status ) && $response->Status == 0 ) {
			return true;
		} else {
			$response = "خطا: " . ! empty( $response->Message ) ? $response->Message : "نامعتبر از وبسرویس.";
		}

		return $response;
	}
}
