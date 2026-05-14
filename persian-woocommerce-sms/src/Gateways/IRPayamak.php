<?php

namespace PW\PWSMS\Gateways;

class IRPayamak extends Gateway {

	public static function id(): string {
		return 'irpayamak';
	}

	public static function name(): string {
		return 'IrPayamak.com - آی آر پیامک';
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

		$to = implode( '-', $to );
		$to = str_ireplace( '+98', '0', $to );

		$url = 'http://irpayamak.com/API/SendSms.ashx?username=' . $username . '&password=' . $password . '&from=' . $from . '&to=' . $to . '&message=' . urlencode( trim( $massage ) );

		$remote = wp_remote_get( $url );

		$response = wp_remote_retrieve_body( $remote );

		if ( preg_match( '/\[.*\]/is', (string) $response ) ) {
			return true; // Success
		}

		return $response;
	}
}
