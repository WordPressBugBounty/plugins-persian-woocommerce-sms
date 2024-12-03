<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class GamaSystems implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'gamasystems';
    }

    public static function name() {
        return 'gama.systems';
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
            'username' => rawurlencode( $username ),
            'password' => rawurlencode( $password ),
            'to'       => rawurlencode( $to ),
            'from'     => rawurlencode( $from ),
            'text'     => $massage,
        ];

        $remote = wp_remote_get( 'http://sms.gama.systems/url/post/SendSMS.ashx?' . http_build_query( $data ) );

        $response = wp_remote_retrieve_body( $remote );

        if ( ! empty( $response ) && $response >= 11 ) {
            return true; // Success
        }

        return $response;
    }
}
