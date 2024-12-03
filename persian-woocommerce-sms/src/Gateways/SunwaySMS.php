<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SunwaySMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'sunwaysms';
    }

    public static function name() {
        return 'sunwaysms.com';
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

            $data = [
                'username' => $username,
                'password' => $password,
                'from'     => $from,
                'to'       => $mobile,
                'message'  => urlencode( $massage ),
            ];

            $remote = wp_remote_get( 'http://sms.sunwaysms.com/SMSWS/HttpService.ashx?' . http_build_query( $data ) );

            $response = wp_remote_retrieve_body( $remote );

            if ( empty( $response ) || $response < 10000 ) {
                $errors[] = $response;
            }
        }

        if ( empty( $errors ) ) {
            return true; // Success
        }

        return $errors;
    }
}
