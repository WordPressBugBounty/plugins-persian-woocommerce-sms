<?php

namespace PW\PWSMS\Gateways;

class GamaSystems extends Gateway {

	public static function id(): string {
		return 'gamasystems';
	}

	public static function name(): string {
		return 'gama.systems';
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
			'username' => rawurlencode( $username ),
			'password' => rawurlencode( $password ),
			'to'       => rawurlencode( $to ),
			'from'     => rawurlencode( $from ),
			'text'     => $massage,
		];

		$remote = wp_remote_get( 'http://sms.gama.systems/url/post/SendSMS.ashx?' . http_build_query( $data ) );

		$response = wp_remote_retrieve_body( $remote );

		if ( ! empty( $response ) && $response >= 11 ) {
			return true; // Success
		}

		return $response;
	}
}
