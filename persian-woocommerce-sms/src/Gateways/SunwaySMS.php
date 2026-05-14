<?php

namespace PW\PWSMS\Gateways;

class SunwaySMS extends Gateway {

	public static function id(): string {
		return 'sunwaysms';
	}

	public static function name(): string {
		return 'SunWaySMS.com - راه آفتاب';
	}

	public function send() {
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$errors = [];

		foreach ( $this->mobile as $mobile ) {

			$data = [
				'username' => $username,
				'password' => $password,
				'from'     => $from,
				'to'       => $mobile,
				'message'  => urlencode( $massage ),
			];

			$remote = wp_remote_get( 'http://sms.sunwaysms.com/SMSWS/HttpService.ashx?' . http_build_query( $data ) );

			$response = wp_remote_retrieve_body( $remote );

			if ( empty( $response ) || $response < 10000 ) {
				$errors[] = $response;
			}
		}

		if ( empty( $errors ) ) {
			return true; // Success
		}

		return $errors;
	}
}
