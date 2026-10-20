<?php

namespace PW\PWSMS\Gateways;

use SoapClient;
use Throwable;

class PayamSMS extends Gateway {

	public static function id(): string {
		return 'payamsms';
	}

	public static function name(): string {
		return 'PayamSMS.com - پیام اس ام اس';
	}

	public function send() {
		$response   = false;
		$username   = $this->username;
		$password   = $this->password;
		$from       = $this->senderNumber;
		$massage    = $this->message;
		$to         = $this->mobile;
		$orginpayam = 'sazmansht';

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

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
			$to[ $i ] = '98' . $ret;
		}

		try {

			$client = new SoapClient(
				'https://new.payamsms.com/services/v2/?wsdl',
				[
					'encoding' => 'UTF-8',
				]
			);

			$results = $client->Send( [
				'organization' => $orginpayam,
				'username'     => $username,
				'password'     => $password,
				'srcNumber'    => $from,
				'body'         => $massage,
				'destNo'       => $to,
				'flash'        => '0',
			] );

			$error = [];
			foreach ( $results as $result ) {
				if ( ! isset( $result->Mobile ) || stripos( $result->ID, 'e' ) !== false ) {
					$error[] = $result;
				}
			}

			if ( empty( $error ) ) {
				return true; // Success
			} else {
				foreach ( $results as $value ) {

					print_r( $value );
				}
			}

		} catch ( Throwable $e ) {
			$response = $e->getMessage();
		}

		return $response;
	}

}
