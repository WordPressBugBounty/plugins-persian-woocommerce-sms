<?php

namespace PW\PWSMS\Gateways;

class SMSNegarIR extends Gateway {

	public static function id(): string {
		return 'smsnegarir';
	}

	public static function name(): string {
		return 'SMSNegar.ir - اس ام اس نگار';
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
			'username'      => rawurlencode( $username ),
			'password'      => rawurlencode( $password ),
			'domain'        => 'sms.smsnegar',
			'reciverNumber' => rawurlencode( $to ),
			'senderNumber'  => rawurlencode( $from ),
			'smsText'       => $massage,
		];

		$remote = wp_remote_get( 'http://sms.smsnegar.ir/sendSMSURL.aspx?' . http_build_query( $data ) );

		$response = wp_remote_retrieve_body( $remote );

		if ( ! empty( $response ) && $response >= 8 ) {
			return true; // Success
		}

		return $response;
	}
}
