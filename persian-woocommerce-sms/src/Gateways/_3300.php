<?php

namespace PW\PWSMS\Gateways;

use SoapClient;
use Throwable;

class _3300 extends Gateway {

	public static function id(): string {
		return '_3300';
	}

	public static function name(): string {
		return 'SMS.3300.ir - نگین ارتباط';
	}

	public function send() {

		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$massage  = $this->message;


		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$to = $this->mobile;

		try {

			$client = new SoapClient(
				'https://sms.3300.ir/almassms.asmx?wsdl',
				[
					'encoding'   => 'UTF-8',
				]
			);

			$param = [
				'pUsername' => $username,
				'pPassword' => $password,
				'line'      => $from,
				'messages'  => [ 'string' => ( $massage ) ],
				'mobiles'   => [ 'string' => $to ],
				'Encodings' => [ 'int' => 2 ],
				'mclass'    => [ 'int' => 1 ],
			];

			$result = $client->Send( $param );

			$result = $result->SendResult ?? 0;

			if ( $result < 0 ) {
				return true; // Success
			} else {
				$response = $result;
			}

		} catch ( Throwable $e ) {
			$response = $e->getMessage();
		}

		return $response;
	}

}
