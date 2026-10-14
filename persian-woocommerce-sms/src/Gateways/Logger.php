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
			'mobile'  => array_map(
				function ( $mobile ) {
					return substr( $mobile, 0, 4 ) . '***' . substr( $mobile, - 4 );
				},
				$this->mobile
			),
			'message' => $this->message,
		] );

		return true;
	}

	public function logVariables( $args ) {

		self::log( PHP_EOL . '######## ' . date( 'Y-m-d H:i:s' ) );

		foreach ( $args as $key => $value ) {
			self::log( "$key: " . print_r( $value, true ) );
		}

	}

	public function log( $message ) {
		error_log( $message . PHP_EOL, 3, wp_upload_dir()['basedir'] . '/wc-logs/pwsms.log' );
	}

}