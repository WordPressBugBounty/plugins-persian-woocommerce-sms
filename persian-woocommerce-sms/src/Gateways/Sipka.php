<?php

namespace PW\PWSMS\Gateways;


/**
 * The api key will set in username field like :
 * username;apikey
 */
class Sipka implements GatewayInterface {
	use GatewayTrait;

	public string $token;

	public static function id() {
		return 'sipka';
	}

	public static function name() {
		return 'sipka.co';
	}

	public function send() {
		$this->senderNumber = trim( $this->senderNumber ) ?: '';

		if ( empty( $this->mobile ) || empty( $this->message ) || empty( $this->senderNumber ) ) {
			return 'شماره گیرنده/ متن پیام یا شماره ارسال کننده، تعریف نشده است.';
		}

		$credentials = explode( ';', $this->username );

		if ( count( $credentials ) !== 2 ) {
			return 'لطفا در فیلد نام کاربری وبسرویس، کلید رابط نرم افزاری را پس از ; ثبت نمایید: نام کاربری;کلید رابط نرم افزار';
		}

		$this->username = trim( $credentials[0] );
		$this->token    = trim( $credentials[1] );

		if ( empty( $this->username ) || empty( $this->token ) || empty( $this->password ) ) {
			return 'لطفا اطلاعات کاربری را در فیلد تنظیمات وبسرویس وارد نمایید.';
		}

		$payload = [
			'username' => $this->username,
			'password' => $this->password,
			'from'     => $this->senderNumber,
			'api'      => $this->token,
		];

		foreach ( $this->mobile as $i => $number ) {

			$payload['to'][ $i ]   = $number;
			$payload['text'][ $i ] = $this->message;

		}

		$response = wp_remote_post( 'https://sipka.co/api/json/sendgrouppost', [
			'method'  => 'POST',
			'body'    => $payload,
			'timeout' => 10,
		] );

		if ( is_wp_error( $response ) ) {
			return 'خطا در اتصال به سرویس: ' . $response->get_error_message();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! $body ) {
			return 'خطا در دریافت پاسخ از سرویس.';
		}

		if ( ! isset( $body['success'] ) || ! $body['success'] ) {
			return $body['response'] ?? 'خطای نامشخص در ارسال پیامک';
		}

		return true;
	}

}
