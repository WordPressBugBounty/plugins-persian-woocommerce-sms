<?php

namespace PW\PWSMS\Services;

use PW\PWSMS\Product\Events as ProductEvents;

class EventService {

	public function __construct() {
		new ProductEvents();
	}
}