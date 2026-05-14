<?php

namespace PW\PWSMS\Gateways;

class Trez extends Gateway {

	public static function id(): string {
		return 'trez';
	}

	public static function name(): string {
		return 'SMSPanel.Trez.ir - ترز';
	}

	public function send() {
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$to = implode( '-', $this->mobile );

		$data = [
			'Smsclass'    => 1,
			'Username'    => rawurlencode( $username ),
			'Password'    => rawurlencode( $password ),
			'RecNumber'   => rawurlencode( $to ),
			'PhoneNumber' => rawurlencode( $from ),
			'MessageBody' => $massage,
		];

		$remote = wp_remote_get( 'http://smspanel.trez.ir/SendGroupMessageWithUrl.ashx?' . http_build_query( $data ) );

		$response = wp_remote_retrieve_body( $remote );

		if ( ! empty( $response ) && $response >= 2000 ) {
			return true; // Success
		}

		return $response;
	}
}
