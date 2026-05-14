<?php

namespace PW\PWSMS\Gateways;

class AsiaSMS extends Gateway {

	public static function id(): string {
		return 'asiasms';
	}

	public static function name(): string {
		return 'AsiaSMS.ir - آسیا اس ام اس';
	}

	public function send() {
		$response = false;
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;

		$massage = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$data = [
			'Username'  => $username,
			'password'  => $password,
			'Receivers' => implode( ',', $this->mobile ),
			'SmsText'   => $massage,
			'SenderId'  => $from,
		];

		$remote = wp_remote_get( 'http://api.asiasms.ir:8080/Messages/SendViaURL?' . http_build_query( $data ) );

		$response = wp_remote_retrieve_body( $remote );

		$result = json_decode( $response, true );

		if ( $result["IsSuccessful"] == true ) {
			$response = true;
		} else {
			return $response; // Success
		}

		return $response;
	}
}
