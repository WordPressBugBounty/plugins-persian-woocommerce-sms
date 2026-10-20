<?php

namespace PW\PWSMS\Gateways;

use SoapClient;
use Throwable;

class AvalPayam extends Gateway {

	public static function id(): string {
		return 'avalpayam';
	}

	public static function name(): string {
		return 'AvalPayam.com - اول پیام';
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

		$i = sizeOf( $to );
		while ( $i -- ) {
			$uNumber = trim( $to[ $i ] );
			$ret     = &$uNumber;
			if ( substr( $uNumber, 0, 3 ) == '%2B' ) {
				$ret = substr( $uNumber, 3 );
			}
			if ( substr( $uNumber, 0, 3 ) == '%2b' ) {
				$ret = substr( $uNumber, 3 );
			}
			if ( substr( $uNumber, 0, 4 ) == '0098' ) {
				$ret = substr( $uNumber, 4 );
			}
			if ( substr( $uNumber, 0, 3 ) == '098' ) {
				$ret = substr( $uNumber, 3 );
			}
			if ( substr( $uNumber, 0, 3 ) == '+98' ) {
				$ret = substr( $uNumber, 3 );
			}
			if ( substr( $uNumber, 0, 2 ) == '98' ) {
				$ret = substr( $uNumber, 2 );
			}
			if ( substr( $uNumber, 0, 1 ) == '0' ) {
				$ret = substr( $uNumber, 1 );
			}
			$to[ $i ] = '+98' . $ret;
		}

		try {

			$client = new SoapClient(
				'https://www.avalpayam.com/class/sms/webservice/server.php?wsdl',
				[
					'encoding'       => 'UTF-8',
					'login'          => $username,
					'password'       => $password,
					'authentication' => SOAP_AUTHENTICATION_BASIC,
				]
			);

			$parameters = [
				'from'       => $from,
				'rcpt_array' => $to,
				'msg'        => $massage,
				'type'       => 'normal',
			];

			$result = $client->enqueue( $parameters );

		} catch ( Throwable $e ) {
			$result = $e->getMessage();
		}

		if ( is_object( $result ) && ( isset( $result->state ) && $result->state == 'done' ) && ( isset( $result->errnum ) && ( $result->errnum == '100' || $result->errnum == 100 ) ) ) {
			return true; // Success
		}

		return $result;
	}

}
