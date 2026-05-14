<?php

namespace PW\PWSMS\Gateways;

use Exception;

class AradITC extends Gateway {

	public static function id(): string {
		return 'araditc';
	}

	public static function name(): string {
		return 'arad itc - آراد آی تی سی';
	}

	public function send() {
		$baseurl = $this->username;
		$apikey  = $this->password;
		$from    = $this->senderNumber;
		$massage = $this->message;

		if ( empty( $username ) ) {
			return false;
		}
		$to = implode( '', $this->mobile );
		$to = preg_replace( '#^(\+98|0)?#', '', $to );

		$body = [
			'SourceAddress'      => $from,
			'MessageText'        => $massage,
			'DestinationAddress' => $to,
		];


		$args = [
			'body'        => json_encode( $body ),
			'timeout'     => '45',
			'headers'     => [
				"Content-Type"  => "application/json",
				"Accept"        => "application/json",
				"Authorization" => "Bearer 4|" . $apikey,
			],
			'data_format' => 'body',
		];

		try {

			$remote = wp_remote_post( $baseurl, $args );

			$response = json_decode( wp_remote_retrieve_body( $remote ) );


		} catch ( Exception $ex ) {
			return "error";
		}

		if ( $response->message == 'عملیات با موفقیت انجام شد' ) {
			return true;
		} else {
			$response = $response->message;
		}

		return $response;
	}
}
