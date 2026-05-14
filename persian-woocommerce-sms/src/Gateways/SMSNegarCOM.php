<?php

namespace PW\PWSMS\Gateways;

use SoapClient;

class SMSNegarCOM extends Gateway {

	public static function id(): string {
		return 'smsnegar';
	}

	public static function name(): string {
		return 'SMS.SMSNegar.com - اس ام اس نگار';
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

		$to = implode( '', $this->mobile );
		$to = preg_replace( '#^(\+98|0)?#', '', $to );

		try {

			$client = new SoapClient( "http://sms.smsnegar.com/webservice/Service.asmx?wsdl" );

			$result = $client->SendSms( [
				"cUserName"     => $username,
				"cPassword"     => $password,
				"cBody"         => $massage,
				"cSmsnumber"    => $to,
				"cGetid"        => "0",
				"nCMessage"     => "1",
				"nTypeSent"     => "1",
				"m_SchedulDate" => "",
				"cDomainname"   => "yazd",
				"nSpeedsms"     => "0",
				"nPeriodmin"    => "0",
				"cstarttime"    => "",
				"cEndTime"      => "",
			] );

			if ( ! empty( $result->SendSmsResult ) ) {


				return true;

				$results = explode( ',', $result );
				unset( $result );

				foreach ( $results as $result ) {
					if ( intval( $result ) > 1000 ) {
						$result       = $client->ShowError( [ "cErrorCode" => $result, "cLanShow" => "FA" ] );
						$sms_response = ! empty( $result->ShowErrorResult ) ? $result->ShowErrorResult : $results;
						break;
					}
				}
			} else {
				$sms_response = 'unknown';
			}
		} catch ( Exception $ex ) {
			$sms_response = $ex->getMessage();
		}

		if ( empty( $sms_response ) ) {
			return true; // Success
		}

		return $sms_response;
	}
}
