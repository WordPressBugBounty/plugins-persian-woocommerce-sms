<?php

namespace PW\PWSMS\Gateways;

use DateTime;
use DateTimeZone;
use PW\PWSMS\PWSMS;

class MaxSMS implements GatewayInterface {
	use GatewayTrait;

	public string $api_url = 'https://api2.ippanel.com/api/v1';

	public static function id() {
		return 'maxsms-normal';
	}

	public static function name() {
		return 'ippanel.co (maxsms.co) خدماتی عادی';
	}

	public function send() {
		$single_api_url = $this->api_url . '/sms/send/webservice/single';
		$api_key        = ! empty( $this->username ) ? $this->username : $this->password;
		$message        = $this->message;
		$from           = $this->senderNumber;
		$recipients     = $this->mobile;
		if ( empty( $from ) ) {
			$from = '+983000505';
		}


		if ( empty( $api_key ) || empty( $message ) || empty( $recipients ) ) {
			return 'اطلاعات پنل، یا پیامک به درستی وارد نشده.';
		}

		$date_time_now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$date_time_now->modify( '+30 seconds' );
		$date_time = $date_time_now->format( 'Y-m-d\TH:i:s.v\Z' );


		$data = [
			'recipient' => $recipients,
			'sender'    => $from,
			'message'   => $message,
			'time'      => $date_time
		];

		$headers = [
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'apikey'       => $api_key,
		];

		$remote = wp_remote_post( $single_api_url, [
			'headers' => $headers,
			'body'    => wp_json_encode( $data ),
		] );

		if ( is_wp_error( $remote ) ) {
			return $remote->get_error_message();
		}

		$response_message = wp_remote_retrieve_response_message( $remote );
		$response_code    = wp_remote_retrieve_response_code( $remote );

		if ( empty( $response_code ) || 200 != $response_code ) {
			return $response_code . ' -> ' . $response_message;
		}

		$response = wp_remote_retrieve_body( $remote );

		if ( empty( $response ) ) {
			return 'بدون پاسخ دریافتی از سمت وب سرویس.';
		}

		$response_data = json_decode( $response, true );

		if ( ! empty( json_last_error() ) ) {
			return 'فرمت نامعتبر پاسخ از سمت وب سرویس.';
		}

		if ( isset( $response_data['status'] ) && strtolower( $response_data['status'] ) == 'ok' ) {
			return true;
		}


		return $response;
	}
}
