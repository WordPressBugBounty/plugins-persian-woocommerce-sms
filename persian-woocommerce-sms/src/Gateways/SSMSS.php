<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SSMSS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'ssmss';
    }

    public static function name() {
        return 'ssmss.ir';
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
            'username'        => rawurlencode( $username ),
            'password'        => rawurlencode( $password ),
            'receiver_number' => rawurlencode( $to ),
            'sender_number'   => rawurlencode( $from ),
            'note'            => $massage,
        ];

        $remote = wp_remote_get( 'http://ssmss.ir/send_via_get/send_sms.php?' . http_build_query( $data ) );

        $response = wp_remote_retrieve_body( $remote );

        if ( ! empty( $response ) && $response >= 1 ) {
            return true; // Success
        }

        return true;
    }
}
