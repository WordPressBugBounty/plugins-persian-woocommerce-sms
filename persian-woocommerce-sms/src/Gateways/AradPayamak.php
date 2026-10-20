<?php

namespace PW\PWSMS\Gateways;

use SoapClient;
use Throwable;

class AradPayamak extends Gateway {

	public static function id(): string {
		return 'aradpayamak';
	}

	public static function name(): string {
		return 'AradPayamak.net - آراد پیامک';
	}

	public function send() {
		$response = false;
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;

		$massage = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$to = implode( ';', $this->mobile );

		try {

			$client = new SoapClient( "https://aradpayamak.net/APPs/SMS/WebService.php?wsdl" );

			$sendsms_parameters = [
				'domain'   => 'aradpayamak.net',
				'username' => $username,
				'password' => $password,
				'from'     => $from,
				'to'       => $to,
				'text'     => $massage,
				'isflash'  => 0,
			];

			$sms_response = $client->sendSMS( $sendsms_parameters );

			if ( ! empty( $sms_response ) ) {
				return true; // Success
			}

		} catch ( Throwable $e ) {
			$sms_response = $e->getMessage();
		}

		if ( $response !== true ) {
			$response = $sms_response;
		}

		return $response;
	}
}
