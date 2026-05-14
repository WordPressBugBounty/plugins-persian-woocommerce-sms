<?php

namespace PW\PWSMS\Gateways;

class NetPaydar extends Gateway {

	public static function id(): string {
		return 'netpaydar';
	}

	public static function name(): string {
		return 'SMS.NetPaydar.com - شبکه پایدار';
	}

	public function send() {
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$to = implode( ',', $this->mobile );

		$content = 'user=' . rawurlencode( $username ) .
		           '&pass=' . rawurlencode( $password ) .
		           '&to=' . rawurlencode( $to ) .
		           '&lineNo=' . rawurlencode( $from ) .
		           '&text=' . $massage;

		$remote = wp_remote_get( 'http://sms.netpaydar.com/SendMessage.ashx?' . $content );

		$response = wp_remote_retrieve_body( $remote );

		if ( strtolower( $response ) == '1' || stripos( $response, 'ارسال با موفقیت انجام شد' ) !== false ) {
			return true; // Success
		}

		return $response;
	}
}
