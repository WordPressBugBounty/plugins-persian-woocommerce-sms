<?php

namespace PW\PWSMS\Gateways;


class Logger extends Gateway {

	public static function id(): string {
		return 'logger';
	}

	public static function name(): string {
		return 'pwsms.log - مخصوص وبمستران و توسعه دهندگان';
	}

	public function send(): bool {

		$this->logVariables( [
			'username'     => $this->username,
			'password'     => $this->password,
			'senderNumber' => $this->senderNumber,
			'mobile'       => $this->mobile,
			'message'      => $this->message,
		] );

		return true;
	}

	protected function logVariables( $args ) {

		self::log( PHP_EOL . '######## ' . date( 'Y-m-d H:i:s' ) );

		foreach ( $args as $key => $value ) {
			self::log( "$key: " . print_r( $value, true ) );
		}

	}

	protected function log( $message ) {
		error_log( $message . PHP_EOL, 3, wp_upload_dir()['basedir'] . '/wc-logs/pwsms.log' );
	}

}