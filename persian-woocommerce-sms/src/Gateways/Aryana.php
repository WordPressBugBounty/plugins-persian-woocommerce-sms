<?php

namespace PW\PWSMS\Gateways;

class Aryana extends Gateway {

	public static function id(): string {
		return 'aryana';
	}

	public static function name(): string {
		return 'PayamKotah.com - پیام کوتاه';
	}

	public function send() {
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$to = implode( '-', $this->mobile );

		$data = [
			'user'    => rawurlencode( $username ),
			'pass'    => rawurlencode( $password ),
			'mobile'  => rawurlencode( $to ),
			'line'    => rawurlencode( $from ),
			'message' => $massage,
			'flash'   => 1,
		];

		$remote = wp_remote_get( 'http://www.payamkotah.ir/FastSendSMS.ashx?' . http_build_query( $data ) );

		$response = wp_remote_retrieve_body( $remote );

		if ( ! empty( $response ) && $response >= 2000 ) {
			return true; // Success
		}

		return $response;
	}
}
