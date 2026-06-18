<?php

namespace PW\PWSMS\Gateways;

use DateTime;
use DateTimeZone;

class MaxSMS extends IPPanelToken {

	public static function id(): string {
		return 'maxsms';
	}

	public static function name(): string {
		return 'MaxSMS.co - مکس اس ام اس';
	}

}
