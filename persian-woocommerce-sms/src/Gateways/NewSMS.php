<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class NewSMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'newsms';
    }

    public static function name() {
        return 'newsms.ir';
    }

    public function send() {
        $response = false;
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = implode( ',', $this->mobile );

        $url = 'http://newsms.ir/api/?action=SMS_SEND&username=' . rawurlencode( $username ) .
               '&password=' . rawurlencode( $password ) .
               '&API_CHANGE_ALLOW=true&to=' . rawurlencode( $to ) .
               '&api=1&from=' . rawurlencode( $from ) .
               '&FLASH=0&text=' . $massage;

        $remote = wp_remote_get( $url );

        $response = wp_remote_retrieve_body( $remote );

        if ( strtolower( $response ) == '1' || $response == 1 ) {
            return true; // Success
        }

        return $response;
    }
}
