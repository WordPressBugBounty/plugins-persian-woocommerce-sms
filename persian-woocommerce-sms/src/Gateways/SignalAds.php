<?php

namespace PW\PWSMS\Gateways;

class SignalAds extends Gateway {

	public static function id(): string {
		return 'signalads';
	}

	public static function name(): string {
		return 'Panel.SignalAds.com - سیگنال ادز';
	}

	public function send() {
		$response = false;
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$to = implode( ',', $this->mobile );

		$url = "https://panel.signalads.com/webservice/url/send.php?method=sendsms?from=$from&to=$to&text=$massage&username=$username&password=$password&type=0&format=json";

		$remote = wp_remote_get( $url );

		$response = wp_remote_retrieve_body( $remote );

		if ( intval( $response ) > 1 ) {
			return true; // Success
		} else {
			return $response;
		}

		return $response;
	}
}
