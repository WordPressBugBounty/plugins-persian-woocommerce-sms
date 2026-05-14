<?php

namespace PW\PWSMS\Gateways;

class PayamResan extends Gateway {

	public static function id(): string {
		return 'payamresan';
	}

	public static function name(): string {
		return 'payam-resan.com';
	}

	public function send() {
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$to = implode( ',', $this->mobile );

		$url = 'http://www.payam-resan.com/APISend.aspx?UserName=' . rawurlencode( $username ) .
		       '&Password=' . rawurlencode( $password ) .
		       '&To=' . rawurlencode( $to ) .
		       '&From=' . rawurlencode( $from ) .
		       '&Text=' . $massage;

		$remote = wp_remote_get( $url );

		$response = wp_remote_retrieve_body( $remote );

		if ( strtolower( $response ) == '1' || $response == 1 ) {
			return true; // Success
		}

		return $response;
	}
}
