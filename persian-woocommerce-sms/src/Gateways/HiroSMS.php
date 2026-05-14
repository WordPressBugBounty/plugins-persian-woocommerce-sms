<?php

namespace PW\PWSMS\Gateways;

use SoapClient;
use SoapFault;

class HiroSMS extends Gateway {

	public static function id(): string {
		return 'hiro_sms';
	}

	public static function name(): string {
		return 'Hiro-SMS.com - هیرو اس ام اس';
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

		try {

			$soap = new SoapClient( "http://my.hiro-sms.com/wbs/send.php?wsdl" );

			$soap->Username = $username;
			$soap->Password = $password;
			$soap->fromNum  = $from;
			$soap->toNum    = $to;
			$soap->Content  = $massage;
			$soap->Type     = '0';

			$result = $soap->SendSMS( $soap->fromNum, $soap->toNum, $soap->Content, $soap->Type, $soap->Username,
				$soap->Password );

			if ( ! empty( $result[0] ) && $result[0] > 100 ) {
				return true; // Success
			} else {
				$response = $result;
			}

			return $response;

		} catch ( SoapFault $e ) {
			return $e->getMessage();
		}
	}
}
