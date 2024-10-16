<?php

namespace PW\PWSMS\Gateways;

use ReflectionClass;

defined( 'ABSPATH' ) || exit;

trait GatewayTrait {

    public array $mobile = [];
    public string $message = '';
    public string $username;
    public string $password;
    public string $senderNumber;

    public function __construct() {
        $this->username     = PWSMS()->get_option( 'sms_gateway_username' );
        $this->password     = PWSMS()->get_option( 'sms_gateway_password' );
        $this->senderNumber = PWSMS()->get_option( 'sms_gateway_sender' );
    }

    /**
     * @return string
     */
    public function get_message(): string {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function set_message( string $message ): void {
        $this->message = $message;
    }

}
