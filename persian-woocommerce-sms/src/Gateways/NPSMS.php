<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class NPSMS implements GatewayInterface {
	use GatewayTrait;

	public static function id() {
		return 'npsms';
	}

	public static function name() {
		return 'npsms.com';
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
