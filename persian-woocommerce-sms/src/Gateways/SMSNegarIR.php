<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SMSNegarIR implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'smsnegarir';
    }

    public static function name() {
        return 'smsnegar.ir';
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
            'username'      => rawurlencode( $username ),
            'password'      => rawurlencode( $password ),
            'domain'        => 'sms.smsnegar',
            'reciverNumber' => rawurlencode( $to ),
            'senderNumber'  => rawurlencode( $from ),
            'smsText'       => $massage,
        ];

        $remote = wp_remote_get( 'http://sms.smsnegar.ir/sendSMSURL.aspx?' . http_build_query( $data ) );

        $response = wp_remote_retrieve_body( $remote );

        if ( ! empty( $response ) && $response >= 8 ) {
            return true; // Success
        }

        return $response;
    }
}
