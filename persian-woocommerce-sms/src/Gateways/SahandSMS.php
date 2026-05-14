<?php

namespace PW\PWSMS\Gateways;

class SahandSMS extends Gateway {

	public static function id(): string {
		return 'sahandsms';
	}

	public static function name(): string {
		return 'SahandSMS.com - سهند اس ام اس';
	}

	public function send() {
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

		$url = 'http://webservice.sahandsms.com/NewSMSWebService.asmx/SendFromUrl?username=' . $username . '&password=' . $password . '&fromNumber=' . $from . '&toNumber=' . $to . '&message=' . urlencode( trim( $massage ) );

		wp_remote_get( $url );

		return true;
	}
}
