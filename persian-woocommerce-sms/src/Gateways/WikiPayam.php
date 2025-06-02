<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class WikiPayam implements GatewayInterface {
	use GatewayTrait;

	public string $api_url = 'https://sms.asanak.ir/webservice/v2rest';

	public static function id() {
		return 'wikipayam';
	}

	public static function name() {
		return 'wikipayam.ir';
	}

	public function send() {
		$authToken = ! empty( $this->username ) ? trim( $this->username ) : trim( $this->password );
		$from      = $this->senderNumber;
		$to        = $this->mobile;
		$message   = $this->message;

		if ( empty( $authToken ) ) {
			return false;
		}

		$to  = implode( ',', $to );
		$to  = str_ireplace( '+98', '0', $to );
		$url = 'https://wikipayam.ir/api/v1/sms/send';

		$data = json_encode( [
			[
				'sender'   => $from,
				'receiver' => $to,
				'message'  => $message,
			]
		] );

		$args = [
			'body'    => $data,
			'headers' => [
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $authToken,
				'Content-Type'  => 'application/json',
			],
			'timeout' => 15,
		];

		$remote = wp_remote_post( $url, $args );

		if ( is_wp_error( $remote ) ) {
			return "خطا: " . $remote->get_error_message();
		}

		$response = wp_remote_retrieve_body( $remote );
		$response = json_decode( $response );

		if ( json_last_error() ) {
			return 'پاسخ نامعتبر از سمت وبسرویس.';
		}

		return $response;
	}
}
