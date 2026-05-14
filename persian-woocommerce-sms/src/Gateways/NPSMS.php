<?php

namespace PW\PWSMS\Gateways;

class NPSMS extends Gateway {

	public static function id(): string {
		return 'npsms';
	}

	public static function name(): string {
		return 'NPSMS.com - نوین پرداز';
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
			'userName'      => $username,
			'password'      => $password,
			'reciverNumber' => $to,
			'senderNumber'  => $from,
			'smsText'       => $massage,
			'domainName'    => 'npsms',
		];

		$remote = wp_remote_get( 'https://npsms.com/sendSmsViaURL.aspx?' . http_build_query( $data ) );

		$response = wp_remote_retrieve_body( $remote );

		if ( ! empty( $response ) && $response >= 1 ) {
			return true; // Success
		}

		return $response;
	}
}
