<?php

namespace PW\PWSMS\Gateways;

class ParsGreen extends Gateway {

	public static function id(): string {
		return 'parsgreen';
	}

	public static function name(): string {
		return 'ParsGreen.com - پارس گرین';
	}

	public function send() {
		$username = $this->username;
		$from     = $this->senderNumber;
		$massage  = $this->message;

		if ( empty( $username ) ) {
			return false;
		}

		$to = $this->mobile;


		$body = [
			'SmsBody' => $massage,
			'Mobiles' => $to,
		];


		$args = [
			'body'        => json_encode( $body ),
			'timeout'     => '45',
			'headers'     => [
				"Content-Type"  => "application/json; charset=utf-8",
				"Accept"        => "application/json",
				"Authorization" => "basic apikey:" . $username,
			],
			'data_format' => 'body',
		];

		try {

			$remote = wp_remote_post( 'http://sms.parsgreen.ir/Apiv2/Message/SendSms', $args );

			$response = json_decode( wp_remote_retrieve_body( $remote ) );


		} catch ( Exception $ex ) {
			return $response = "error";
		}

		if ( $response->R_Success ) {
			return $response = true;
		} else {
			$response = $response->R_Message;
		}

		return $response;
	}
}
