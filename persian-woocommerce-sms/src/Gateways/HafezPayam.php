<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class HafezPayam implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'hafezpayam';
    }

    public static function name() {
        return 'hafezpayam.com';
    }

    public function send() {
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $errors = [];

        foreach ( $this->mobile as $mobile ) {

            $remote = wp_remote_get( 'http://hafezpayam.com/URLSend.aspx?Username=' . $username . '&Password=' . $password . '&PortalCode=' . $from . '&Mobile=' . $mobile . '&Message=' . urlencode( $massage ) . '&Flash=0' );

            $response = wp_remote_retrieve_body( $remote );

            if ( abs( $response ) < 30 ) {
                $errors[] = $response;
            }
        }

        if ( empty( $errors ) ) {
            return true; // Success
        } else {
            $response = $errors;
        }

        return $response;
    }
}
