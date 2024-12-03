<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class AsiaSMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'asiasms';
    }

    public static function name() {
        return 'asiasms.ir';
    }

    public function send() {
        $response = false;
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;

        $massage = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $data = [
            'Username'  => $username,
            'password'  => $password,
            'Receivers' => implode( ',', $this->mobile ),
            'SmsText'   => $massage,
            'SenderId'  => $from,
        ];

        $remote = wp_remote_get( 'http://api.asiasms.ir:8080/Messages/SendViaURL?' . http_build_query( $data ) );

        $response = wp_remote_retrieve_body( $remote );

        $result = json_decode( $response, true );

        if ( $result["IsSuccessful"] == true ) {
            $response = true;
        } else {
            return $response; // Success
        }

        return $response;
    }
}
