<?php

namespace PW\PWSMS\Gateways;

class MeliPayamakPattern extends MeliPayamak {


	public static function id(): string {
		return 'melipayamakpattern';
	}

	public static function name(): string {
		return 'melipayamak.com - ملی پیامک خدماتی';
	}

}
