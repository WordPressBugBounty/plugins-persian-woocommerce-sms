<?php

namespace PW\PWSMS\Gateways;

class Jarin extends Gateway {

	public static function id(): string {
		return 'jarin';
	}

	public static function name(): string {
		return 'w.jarin.ir';
	}

	public function send() {
		$response = false;
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$to       = $this->mobile;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$param = [
			'phoneNumber'      => $username,
			'passWord'         => $password,
			'text'             => $massage,
			'destPhoneNumbers' => implode( ',', $to ),
			'lines'            => $from,
		];

		$remote = wp_remote_post( "http://wp.jarin.ir/Api/SendMessage.php", [
			'body' => $param,
		] );

		$_response = wp_remote_retrieve_body( $remote );

		$response = json_decode( $response );

		if ( $response->status == 100 ) {
			return true;
		} else {
			return "error : " . $response->status;
		}

		return $response;
	}
}
