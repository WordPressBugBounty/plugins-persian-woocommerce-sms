<?php

namespace PW\PWSMS\Gateways;

use SoapClient;
use Throwable;

class BakhtarPanel extends Gateway {

	public static function id(): string {
		return 'bakhtarpanel';
	}

	public static function name(): string {
		return 'Bakhtar.xyz - باختر پنل';
	}

	public function send() {
		$response = false;
		$username = $this->username;
		$password = $this->password;
		$from     = $this->senderNumber;
		$massage  = $this->message;

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$to = implode( ',', $this->mobile );

		try {

			$client = new SoapClient( 'https://login.bakhtar.xyz/webservice/server.asmx?wsdl' );

			$result = $client->Sendsms( [
				'4',
				$from,
				$username,
				$password,
				'98',
				$massage,
				$to,
				false,
			] );

			$status = explode( ',', strval( $result ?? '' ) );

		} catch ( Throwable $e ) {
			$status = $e->getMessage();
		}

		if ( is_array( $status ) && count( $status ) > 1 && $status[0] == 1 ) {
			return true; // Success
		}

		return $status;
	}

}
