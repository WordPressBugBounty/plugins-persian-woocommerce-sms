<?php

namespace PW\PWSMS\Gateways;

class NikSMS extends Gateway {

	public static function id(): string {
		return 'nicsms';
	}

	public static function name(): string {
		return 'NikSMS.com - نیک اس ام اس';
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
			'username'     => $username,
			'password'     => $password,
			'message'      => $massage,
			'numbers'      => implode( ',', $to ),
			'senderNumber' => $from,
			'sendOn'       => date( 'Y/m/d-h:m' ),
			'sendType'     => 1,
		];

		$remote = wp_remote_post( "http://niksms.com/fa/PublicApi/GroupSms", [
			'body' => $param,
		] );

		$_response = wp_remote_retrieve_body( $remote );

		$_response = json_decode( $_response );
		$_response = ! empty( $_response->Status ) ? $_response->Status : 2;

		if ( $_response === 1 || strtolower( $_response ) == 'successful' ) {
			return true; // Success
		} else {
			$response = $_response;
		}

		return $response;
	}
}
