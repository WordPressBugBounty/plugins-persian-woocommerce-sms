<?php

namespace PW\PWSMS\API;

use WP_REST_Request;

abstract class RestAPI {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	abstract public function register_routes();

	abstract public function permission_callback( WP_REST_Request $request ): bool;

}