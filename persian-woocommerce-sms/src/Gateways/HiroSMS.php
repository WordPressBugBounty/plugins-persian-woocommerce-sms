<?php

namespace PW\PWSMS\Gateways;

use SoapClient;
use Throwable;

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

			$client = new SoapClient(
				'https://my.hiro-sms.com/wbs/send.php?wsdl',
				[
					'encoding' => 'UTF-8',
				]
			);

			$result = $client->SendSMS(
				$from,
				$to,
				$massage,
				'0',
				$username,
				$password
			);

			if ( ! empty( $result[0] ) && $result[0] > 100 ) {
				return true; // Success
			} else {
				$response = $result;
			}

			return $response;

		} catch ( Throwable $e ) {
			return $e->getMessage();
		}
	}
}
