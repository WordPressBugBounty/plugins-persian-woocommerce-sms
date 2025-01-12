<?php

namespace PW\PWSMS\Gateways;


use PW\PWSMS\PWSMS;

class Logger implements GatewayInterface {
	use GatewayTrait;

	public static function id() {
		return 'logger';
	}

	public static function name() {
		return 'pwsms.log';
	}

	public function send() {

		$this->logVariables( $this->username, $this->password, $this->senderNumber, $this->mobile, $this->message );

		return true;
	}

	protected function logVariables( ...$args ) {
		foreach ( $args as $index => $arg ) {
			self::log( PHP_EOL . "Arg $index: " . print_r( $arg, true ) );
		}
		self::log( '######################################################' );
	}

	protected function log( $message ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true );
		}
		error_log( date( 'Y-m-d H:i:s' ) . ' - ' . $message . PHP_EOL, 3, PWSMS_LOG_FILE );
	}

}