<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class PayamResan implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'payamresan';
    }

    public static function name() {
        return 'payam-resan.com';
    }

    public function send() {
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = implode( ',', $this->mobile );

        $url = 'http://www.payam-resan.com/APISend.aspx?UserName=' . rawurlencode( $username ) .
               '&Password=' . rawurlencode( $password ) .
               '&To=' . rawurlencode( $to ) .
               '&From=' . rawurlencode( $from ) .
               '&Text=' . $massage;

        $remote = wp_remote_get( $url );

        $response = wp_remote_retrieve_body( $remote );

        if ( strtolower( $response ) == '1' || $response == 1 ) {
            return true; // Success
        }

        return $response;
    }
}
