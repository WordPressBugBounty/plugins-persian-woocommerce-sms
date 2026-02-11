<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;

class KaveNegar implements GatewayInterface {
	use GatewayTrait;

	public static function id() {
		return 'kavenegar';
	}

	public static function name() {
		return 'kavenegar.com';
	}

	public function send() {
		$api_key = ! empty( trim( $this->username ) ) ? trim( $this->username ) : trim( $this->password );
		$from    = trim( $this->senderNumber );
		$message = trim( $this->message );
		$to      = $this->mobile;

		if ( empty( $api_key ) ) {
			return 'کلید API وارد نشده است.';
		}

		if ( empty( $from ) ) {
			return 'شماره فرستنده وارد نشده است.';
		}

		if ( empty( $message ) ) {
			return 'متن پیام وارد نشده است.';
		}

		if ( empty( $to ) ) {
			return 'شماره گیرنده وارد نشده است.';
		}

		if ( ! is_array( $to ) ) {
			$to = [ $to ];
		} else {
			$to = implode( ',', $to );
		}

		$query_params = [
			'sender'   => $from,
			'receptor' => $to,
			'message'  => $message,
		];

		$url = "https://api.kavenegar.com/v1/{$api_key}/sms/send.json?" . http_build_query( $query_params );

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return 'خطا در برقراری ارتباط با سرور: ' . $response->get_error_message();
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return 'پاسخی از سرور دریافت نشد.';
		}

		$json = json_decode( $body );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return 'خطا در پردازش پاسخ سرور: ' . json_last_error_msg();
		}

		if ( ! empty( $json->return->status ) && $json->return->status == 200 ) {
			return true;
		}

		return 'ارسال پیام با خطا مواجه شد: ' . ( $json->return->message ?? 'پاسخ نامشخص از سرور' );
	}

}
