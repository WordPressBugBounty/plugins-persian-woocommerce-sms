<?php

namespace PW\PWSMS\Gateways;

use SoapClient;
use SoapFault;

class BOne extends Gateway {

	public static function id(): string {
		return 'b1ir';
	}

	public static function name(): string {
		return '1b1.ir - یک به یک';
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

			$client             = new SoapClient( "http://1b1.ir/APPs/SMS/WebService.php?wsdl" );
			$sendsms_parameters = [
				'domain'   => '1b1.ir',
				'username' => $username,
				'password' => $password,
				'from'     => $from,
				'to'       => $to,
				'text'     => $massage,
				'isflash'  => 0,
			];

			$sms_response = call_user_func_array( [ $client, 'sendSMS' ], $sendsms_parameters );

			if ( ! empty( $sms_response ) ) {
				return true; // Success
			}

		} catch ( SoapFault $ex ) {
			$sms_response = $ex->getMessage();
		}

		if ( $response !== true ) {
			$response = $sms_response;
		}

		return $response;
	}
}
