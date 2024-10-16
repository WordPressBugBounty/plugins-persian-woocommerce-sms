<?php

namespace PW\PWSMS\Gateways;


use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class AzaranPayamak implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'azaranpayamak';
    }

    public static function name() {
        return 'Azaranpayamak.ir';
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

        try {
            $client       = new SoapClient( 'http://azaranpayamak.ir/API/Send.asmx?WSDL' );
            $sms_response = $client->SendSms(
                [
                    'username' => $username,
                    'password' => $password,
                    'from'     => $from,
                    'to'       => $to,
                    'text'     => $massage,
                    'flash'    => false,
                    'udh'      => '',
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