<?php

namespace PW\PWSMS\Services;

use PW\PWSMS\API\ContentAPI;
use PW\PWSMS\API\SMSAPI;
use PW\PWSMS\API\SubscriptionAPI;

class APIService {

	public function __construct() {
		new ContentAPI();
		new SMSAPI();
		new SubscriptionAPI();
	}

}