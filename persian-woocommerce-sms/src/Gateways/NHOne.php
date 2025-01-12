<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class NHOne implements GatewayInterface {
	use GatewayTrait;

	public static function id() {
		return 'nh1ir';
	}

	public static function name() {
		return 'nh1.ir';
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
