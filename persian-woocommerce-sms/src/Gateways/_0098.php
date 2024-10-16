<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class _0098 implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return '_0098';
    }

    public static function name() {
        return '0098sms.com';
    }

    public function send() {
        $username  = $this->username;
        $password  = $this->password;
        $from      = $this->senderNumber;
        $recievers = $this->mobile;
        $massage   = $this->message;
        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $errors = [];

        foreach ( (array) $recievers as $to ) {

            $url = 'http://www.0098sms.com/sendsmslink.aspx?DOMAIN=0098' .
                   '&USERNAME=' . rawurlencode( $username ) .
                   '&PASSWORD=' . rawurlencode( $password ) .
                   '&FROM=' . rawurlencode( $from ) .
                   '&TO=' . rawurlencode( $to ) .
                   '&TEXT=' . $massage;

            $remote = wp_remote_get( $url );

            $sms_response = intval( wp_remote_retrieve_body( $remote ) );

            if ( $sms_response !== 0 ) {
                $errors[ $to ] = $sms_response;
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
