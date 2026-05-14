<?php

namespace PW\PWSMS\Gateways;

class Afe extends Gateway {

	public static function id(): string {
		return 'afe';
	}

	public static function name(): string {
		return 'Afe.ir - واید';
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

			$remote = wp_remote_get( 'http://www.afe.ir/Url/SendSMS?username=' . $username . '&Password=' . $password . '&Number=' . $from . '&mobile=' . $mobile . '&sms=' . $massage );

			$response = wp_remote_retrieve_body( $remote );

			if ( empty( $response ) || stripos( $response, 'success' ) === false ) {
				$errors[] = $response;
			}
		}

		if ( empty( $errors ) ) {
			return true; // Success
		}

		return $errors;
	}
}
