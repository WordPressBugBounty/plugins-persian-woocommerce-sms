<?php

namespace PW\PWSMS\Gateways;

class NHOne extends Gateway {

	public static function id(): string {
		return 'nh1ir';
	}

	public static function name(): string {
		return 'NH1.ir - نوین همراه';
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
			'Username' => $username,
			'Password' => $password,
			'To'       => implode( ',', $this->mobile ),
			'Text'     => $massage,
			'From'     => $from,
		];

		$remote = wp_remote_get( 'http://ws.nh1.ir/Api/SMS/Send?' . http_build_query( $data ) );

		$response = wp_remote_retrieve_body( $remote );

		$result = json_decode( $response, true );

		if ( ! empty( $result["code"] ) && ! empty( $result["message"] ) ) {
			return true;
		} else {
			return true; // Success
		}

		return $response;
	}
}
