<?php

namespace PW\PWSMS\Gateways;


use SoapClient;
use Throwable;

class SMSFor extends Gateway {

	public static function id(): string {
		return 'smsfor';
	}

	public static function name(): string {
		return 'SMSFor.ir - ارتباط آفرینان تات';
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
			$to[ $i ] = '0' . $ret;
		}

		try {

			$client = new SoapClient(
				'https://www.smsfor.ir/webservice/soap/smsService.php?wsdl',
				[
					'encoding' => 'UTF-8',
				]
			);

			$params = [
				'username'         => $username,
				'password'         => $password,
				'sender_number'    => [ $from ],
				'receiver_number'  => $to,
				'note'             => [ $massage ],
				'date'             => [],
				'request_uniqueid' => [],
				'flash'            => false,
				'onlysend'         => 'ok',
			];

			$md_res = $client->send_sms( $params );

			if ( empty( $md_res->{'getMessage()'} ) && empty( $md_res->{'getMessage()'} ) && is_numeric( str_ireplace( ',', '', $md_res[0] ) ) ) {
				return true; // Success
			} else {
				$response = $md_res;
			}

		} catch ( Throwable $e ) {
			$response = $e->getMessage();
		}

		return $response;
	}

}
