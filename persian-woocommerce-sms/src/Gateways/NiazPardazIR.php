<?php

namespace PW\PWSMS\Gateways;


use SoapClient;
use SoapFault;

class NiazPardazIR extends Gateway {

	public static function id(): string {
		return 'niazpardaz_';
	}

	public static function name(): string {
		return 'Login.NiazPardaz.ir - نیاز پرداز';
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
			$client       = new SoapClient( "http://payamak-service.ir/SendService.svc?wsdl" );
			$encoding     = "UTF-8";
			$parameters   = [
				'userName'       => $username,
				'password'       => $password,
				'fromNumber'     => $from,
				'toNumbers'      => $to,
				'messageContent' => iconv( $encoding, 'UTF-8//TRANSLIT', $massage ),
				'isflash'        => false,
				'udh'            => "",
				'recId'          => [ 0 ],
				'status'         => 0,
			];
			$sms_response = $client->SendSms( $parameters )->SendSMSResult;

		} catch ( SoapFault $ex ) {
			$sms_response = $ex->getMessage();
		}

		if ( strval( $sms_response ) == '0' ) {
			return true; // Success
		} else {
			$response = $sms_response;
		}

		return $response;
	}

}