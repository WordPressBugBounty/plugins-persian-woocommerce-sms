<?php

namespace PW\PWSMS\Gateways;

use SoapClient;
use Throwable;

class IdehPayam extends Gateway {

	public static function id(): string {
		return 'idehpayam';
	}

	public static function name(): string {
		return 'IdehPayam.com - ایده پیام';
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

			$client = new SoapClient(
				'https://185.112.33.61/webservice/send.php?wsdl',
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
