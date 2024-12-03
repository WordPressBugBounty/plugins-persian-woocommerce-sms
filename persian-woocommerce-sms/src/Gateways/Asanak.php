<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class Asanak implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'asanak';
    }

    public static function name() {
        return 'asanak.ir';
    }

    public function send() {
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $to       = $this->mobile;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = implode( '-', $to );
        $to = str_ireplace( '+98', '0', $to );

        $data = [
            'username'    => $username,
            'password'    => $password,
            'destination' => $to,
            'source'      => $from,
            'message'     => $massage,
        ];

        $remote = wp_remote_get( 'http://panel.asanak.ir/webservice/v1rest/sendsms?' . http_build_query( $data ) );

        $response = wp_remote_retrieve_body( $remote );

        if ( preg_match( '/\[.*\]/is', (string) $response ) ) {
            return true; // Success
        }

        return $response;
    }
}
