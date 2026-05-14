<?php

namespace PW\PWSMS\Gateways;

class ISMS extends Gateway {

	public static function id(): string {
		return 'isms';
	}

	public static function name(): string {
		return 'ISMS.ir - آی اس ام اس';
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
			'username' => $username,
			'password' => $password,
			'mobiles'  => $this->mobile,
			'body'     => $massage,
			'sender'   => $from,
		];

		$remote = wp_remote_get( 'http://ws3584.isms.ir/sendWS?' . http_build_query( $data ) );

		$response = wp_remote_retrieve_body( $remote );

		$result = json_decode( $response, true );

		if ( ! empty( $result["code"] ) && ! empty( $result["message"] ) ) {
			$response = $result;
		} else {
			return true; // Success
		}

		return $response;
	}
}
