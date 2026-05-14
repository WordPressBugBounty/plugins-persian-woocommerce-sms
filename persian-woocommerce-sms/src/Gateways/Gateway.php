<?php

namespace PW\PWSMS\Gateways;

defined( 'ABSPATH' ) || exit;

abstract class Gateway {

	public array $mobile = [];
	public string $message = '';
	public string $username;
	public string $password;
	public string $senderNumber;

	public function __construct() {
		$this->username     = PWSMS()->get_option( 'sms_gateway_username' );
		$this->password     = PWSMS()->get_option( 'sms_gateway_password' );
		$this->senderNumber = PWSMS()->get_option( 'sms_gateway_sender' );
	}

	public static function id(): string {
		return get_called_class();
	}

	abstract public static function name(): string;

	abstract public function send();

	/**
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * @param string $message
	 */
	public function set_message( string $message ): void {
		$this->message = $message;
	}

	/**
	 * Returns the API token (API key can be in username or password)
	 *
	 * @return string (Empty string if nothing provided)
	 */
	public function get_token(): string {
		$username = trim( $this->username );
		$password = trim( $this->password );
		if ( empty( $username ) && empty( $password ) ) {
			return '';
		}
		if ( ! empty( $username ) ) {
			return $username;
		}

		return $password;
	}

	public function is_pattern(): bool {
		return str_starts_with( $this->message, 'pattern:' ) || str_starts_with( $this->message, 'pcode:' ) || str_starts_with( $this->message, 'patterncode:' );
	}

	/**
	 * $message format:
	 *
	 * pattern:<PatternCode>
	 * <Var1>:<Val1>
	 * <Var2>:<Val2>
	 * ...
	 * ...
	 *
	 * @return array
	 */
	public function parse_pattern(): array {

		$result = [
			'code' => '',
			'vars' => [],
		];

		$message = str_replace( [ "\r\n", "\n", "\\r\\n", "\\n" ], '~', $this->message );
		$parts   = explode( '~', $message );

		foreach ( $parts as $part ) {

			[ $key, $value ] = explode( ':', $part, 2 );

			$key   = trim( $key , "}{% \n\r\t\v\x00");
			$value = trim( $value );

			if ( in_array( $key, [ 'pattern', 'pcode', 'patterncode' ] ) ) {
				$result['code'] = $value;
			} elseif ( strlen( $key ) ) {
				$result['vars'][ $key ] = $value;
			}

		}

		return $result;
	}

	public function get_options(): array {
		return [];
	}
}
