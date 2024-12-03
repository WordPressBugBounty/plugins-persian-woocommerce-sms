<?php

namespace PW\PWSMS;

defined( 'ABSPATH' ) || exit;

class Shortcode {

	/**
	 * Shortcode method will do three things:
	 * return the string without brackets
	 * return the string with brackets
	 * echoing the shortcode
	 * @return ?string
	 */
	public static function shortcode( $get = false, $strip_brackets = false ) {
		$shortcode = 'woo_ps_sms';

		if ( $get ) {
			if ( $strip_brackets ) {
				return $shortcode;
			}

			return "[$shortcode]";
		}

		echo do_shortcode( "[$shortcode]" );
	}

}

