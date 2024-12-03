<?php

namespace PW\PWSMS\Gateways;

use ReflectionClass;

defined( 'ABSPATH' ) || exit;

interface GatewayInterface {
    public function __construct();

    public static function id();

    public static function name();

    public function send();

}
