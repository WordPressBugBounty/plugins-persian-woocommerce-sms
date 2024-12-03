<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class KarenKart implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'karenkart';
    }

    public static function name() {
        return 'karenkart.com';
    }

    public function send() {
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = implode( ',', $this->mobile );


        $remote = wp_remote_get( 'http://www.karenkart.com/Home/send_via_get?note=' . $massage . '&username=' . $username . '&password=' . $password . '&receiver_number=' . $to . '&sender_number=' . $from . '' );

        $response = wp_remote_retrieve_body( $remote );

        if ( ! empty( $response ) && $response >= 1 ) {
            return true; // Success
        }

        return $response;
    }
}
