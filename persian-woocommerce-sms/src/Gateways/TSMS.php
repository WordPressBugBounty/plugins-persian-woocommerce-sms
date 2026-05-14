<?php

namespace PW\PWSMS\Gateways;

class TSMS extends Gateway {

	public static function id(): string {
		return 'TSMS';
	}

	public static function name(): string {
		return 'TSMS.ir - طوبی اس ام اس';
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

		$data = [
			'from'     => rawurlencode( $from ),
			'to'       => rawurlencode( $to ),
			'username' => rawurlencode( $username ),
			'password' => rawurlencode( $password ),
			'message'  => $massage,
		];

		$remote = wp_remote_get( 'http://tsms.ir/url/tsmshttp.php?' . http_build_query( $data ) );

		$response = wp_remote_retrieve_body( $remote );

		if ( strtolower( $response ) > '20' ) {
			return true; // Success
		}

		return $response;
	}
}
