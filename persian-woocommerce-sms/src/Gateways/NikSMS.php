<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class NikSMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'nicsms';
    }

    public static function name() {
        return 'niksms.com';
    }

    public function send() {
        $response = false;
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $to       = $this->mobile;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $param = [
            'username'     => $username,
            'password'     => $password,
            'message'      => $massage,
            'numbers'      => implode( ',', $to ),
            'senderNumber' => $from,
            'sendOn'       => date( 'yyyy/MM/dd-hh:mm' ),
            'sendType'     => 1,
        ];

        $remote = wp_remote_post( "http://niksms.com/fa/PublicApi/GroupSms", [
            'body' => $param,
        ] );

        $_response = wp_remote_retrieve_body( $remote );

        $_response = json_decode( $_response );
        $_response = ! empty( $_response->Status ) ? $_response->Status : 2;

        if ( $_response === 1 || strtolower( $_response ) == 'successful' ) {
            return true; // Success
        } else {
            $response = $_response;
        }

        return $response;
    }
}
