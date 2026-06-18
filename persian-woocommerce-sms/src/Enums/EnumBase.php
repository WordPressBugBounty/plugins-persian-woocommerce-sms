<?php

namespace PW\PWSMS\Enums;

defined( 'ABSPATH' ) || exit;

abstract class EnumBase {

	public $value = null;

	public function __construct( string $value ) {
		$this->value = $value;
	}

	/**
	 * @param string $value
	 *
	 * @return static
	 * @throws \Exception
	 */
	public static function tryFrom( string $value ) {

		$reflectionClass = new \ReflectionClass( static::class );
		$constants       = $reflectionClass->getConstants();

		foreach ( $constants as $constant => $constantValue ) {
			if ( $value === $constantValue ) {
				return new static( $value );
			}
		}
		throw new \Exception( esc_html( "{$value} does not exists in properties" ) );
	}

	/**
	 * @return array
	 */
	public static function cases(): array {
		$reflectionClass = new \ReflectionClass( static::class );
		$constants       = $reflectionClass->getConstants();

		$cases = [];

		foreach ( $constants as $constant => $value ) {
			$cases[ $value ] = new static( $value );
		}

		return $cases;
	}
}