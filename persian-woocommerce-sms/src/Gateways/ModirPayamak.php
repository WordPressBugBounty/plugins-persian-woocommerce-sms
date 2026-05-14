<?php

namespace PW\PWSMS\Gateways;

class ModirPayamak extends IPPanelToken {

	/**
	 * @var array
	 */
	public array $failed_numbers = [];

	public static function id(): string {
		return 'modirpayamak';
	}

	public static function name(): string {
		return 'ModirPayamak.com - مدیر پیامک';
	}
}
