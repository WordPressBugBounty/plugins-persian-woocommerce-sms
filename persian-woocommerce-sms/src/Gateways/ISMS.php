<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class ISMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'isms';
    }

    public static function name() {
        return 'isms.ir';
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
            'username' => $username,
            'password' => $password,
            'mobiles'  => $this->mobile,
            'body'     => $massage,
            'sender'   => $from,
        ];

        $remote = wp_remote_get( 'http://ws3584.isms.ir/sendWS?' . http_build_query( $data ) );

        $response = wp_remote_retrieve_body( $remote );

        $result = json_decode( $response, true );

        if ( ! empty( $result["code"] ) && ! empty( $result["message"] ) ) {
            $response = $result;
        } else {
            return true; // Success
        }

        return $response;
    }
}
