<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SornaSMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'sornasms';
    }

    public static function name() {
        return 'sornasms.net';
    }

    public function send() {
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = implode( '-', $this->mobile );

        $data = [
            'username'     => $username,
            'pass'         => $password,
            'mobile'       => $to,
            'senderNumber' => $from,
            'message'      => $massage,
            'code'         => 10260,
        ];

        $remote = wp_remote_get( 'https://sornasms.net/getCustomer.aspx?' . http_build_query( $data ) );

        $response = wp_remote_retrieve_body( $remote );

        if ( ! empty( $response ) && $response >= 1 ) {
            return true; // Success
        }

        return $response;
    }
}
