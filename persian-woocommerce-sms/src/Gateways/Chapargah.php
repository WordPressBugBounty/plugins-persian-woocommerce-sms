<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class Chapargah implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'chapargah';
    }

    public static function name() {
        return 'chapargah.com';
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

        $massage = iconv( 'UTF-8', 'UTF-8//TRANSLIT', $massage );

        try {
            $client       = new SoapClient( 'http://chapargah.com/API/Send.asmx?WSDL' );
            $sms_response = $client->SendSms(
                [
                    'username' => $username,
                    'password' => $password,
                    'from'     => $from,
                    'to'       => $to,
                    'text'     => $massage,
                    'flash'    => false,
                    'recId'    => [ 0 ],
                    'status'   => 0,
                ]
            )->SendSmsResult;
        } catch ( SoapFault $sf ) {
            $sms_response = $sf->getMessage();
        }
        if ( strval( $sms_response ) == '0' ) {
            return true; // Success
        } else {
            $response = $sms_response;
        }

        return $response;
    }
}
