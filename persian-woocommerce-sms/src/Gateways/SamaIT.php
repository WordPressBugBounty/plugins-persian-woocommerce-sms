<?php

namespace PW\PWSMS\Gateways;

class SamaIT extends Gateway {

	public static function id(): string {
		return 'samait';
	}

	public static function name(): string {
		return 'SamaIT.ir - سما ارتباط اسپادانا';
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
			'username'        => rawurlencode( $username ),
			'password'        => rawurlencode( $password ),
			'receiver_number' => rawurlencode( $to ),
			'sender_number'   => rawurlencode( $from ),
			'note'            => $massage,
		];

		$remote = wp_remote_get( 'http://samait.ir/send_via_get/send_sms.php?' . http_build_query( $data ) );

		$response = wp_remote_retrieve_body( $remote );

		if ( ! empty( $response ) && $response >= 8 ) {
			return true; // Success
		}

		return $response;
	}
}
