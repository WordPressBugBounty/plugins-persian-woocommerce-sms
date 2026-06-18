<?php

namespace PW\PWSMS\Gateways\Features;

use Exception;

interface SendPatternFeature {

	/**
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function send_pattern_sms(): bool;
}