<?php

namespace PW\PWSMS\Gateways;

use SoapClient;
use Throwable;

class SepahanSMS extends Gateway {

	public static function id(): string {
		return 'sepahansms';
	}

	public static function name(): string {
		return 'SepahanSMS.com - سپاهان گستر';
	}

	public function send() {
		$response = false;
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$to       = $this->mobile;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		try {

			$client = new SoapClient( "https://www.sepahansms.com/smsSendWebServiceforphp.asmx?wsdl" );

			$sms_response = $client->SendSms( [
				'UserName'     => $username,
				'Pass'         => $password,
				'Domain'       => 'sepahansms',
				'SmsText'      => [ $massage ],
				'MobileNumber' => $to,
				'SenderNumber' => $from,
				'sendType'     => 'StaticText',
				'smsMode'      => 'SaveInPhone',
			] )->SendSmsResult->long;

			if ( is_array( $sms_response ) || $sms_response > 1000 ) {
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
