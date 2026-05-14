<?php

namespace PW\PWSMS\Gateways;

class _0098 extends Gateway {

	public static function id(): string {
		return '_0098';
	}

	public static function name(): string {
		return '0098SMS.com - ۰۰۹۸ اس ام اس';
	}

	public function send() {
		$username  = $this->username;
		$password  = $this->password;
		$from      = $this->senderNumber;
		$recievers = $this->mobile;
		$massage   = $this->message;
		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$errors = [];

		foreach ( (array) $recievers as $to ) {

			$url = 'http://www.0098sms.com/sendsmslink.aspx?DOMAIN=0098' .
			       '&USERNAME=' . rawurlencode( $username ) .
			       '&PASSWORD=' . rawurlencode( $password ) .
			       '&FROM=' . rawurlencode( $from ) .
			       '&TO=' . rawurlencode( $to ) .
			       '&TEXT=' . $massage;

			$remote = wp_remote_get( $url );

			$sms_response = intval( wp_remote_retrieve_body( $remote ) );

			if ( $sms_response !== 0 ) {
				$errors[ $to ] = $sms_response;
			}
		}

		if ( empty( $errors ) ) {
			return true; // Success
		} else {
			$response = $errors;
		}

		return $response;
	}
}
