<?php

namespace PW\PWSMS\Gateways;

class IranSMS extends Gateway {

	public static function id(): string {
		return 'iransms';
	}

	public static function name(): string {
		return 'IranSMS.co - ایران اس ام اس';
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

			$remote = wp_remote_get( 'http://www.iransms.co/URLSend.aspx?Username=' . $username . '&Password=' . $password . '&PortalCode=' . $from . '&Mobile=' . $mobile . '&Message=' . $massage . '&Flash=0' );

			$response = wp_remote_retrieve_body( $remote );

			if ( abs( $response ) < 30 ) {
				$errors[] = $response;
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
