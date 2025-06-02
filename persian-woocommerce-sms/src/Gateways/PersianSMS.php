<?php

namespace PW\PWSMS\Gateways;

use Exception;
use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class PersianSMS implements GatewayInterface {
	use GatewayTrait;

	public static function id() {
		return 'persian-sms';
	}

	public static function name() {
		return 'persian-sms.com';
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

		$to      = implode( ",", $to );
		$massage = urlencode( $massage );

		try {

			$data = [
				'username'         => $username,
				'password'         => $password,
				'text'             => $massage,
				'to'               => $to,
				'from'             => $from,
				'action'           => 'SMS_SEND',
				'FLASH'            => 0,
				'API_CHANGE_ALLOW' => true,
				'api'              => 6,
			];

			$remote = wp_remote_get( 'http://persian-sms.com/api/?' . http_build_query( $data ) );

			$response = json_decode( wp_remote_retrieve_body( $remote ) );

			if ( isset( $results->error ) ) {
				$response = $results->error;
			} elseif ( ! empty( $results->result ) && $results->result && ! empty( $results->list ) ) {
				return true; // Success
			}

			return $response;

		} catch ( Exception $ex ) {
			return $ex->getMessage();
		}
	}
}
