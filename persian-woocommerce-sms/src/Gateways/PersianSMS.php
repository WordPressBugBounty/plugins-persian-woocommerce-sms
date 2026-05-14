<?php

namespace PW\PWSMS\Gateways;

use Exception;

class PersianSMS extends Gateway {

	public static function id(): string {
		return 'persian-sms';
	}

	public static function name(): string {
		return 'Persian-SMS.com - پرشین اس ام اس';
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
