<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class AradPayamak implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'aradpayamak';
    }

    public static function name() {
        return 'aradpayamak.net';
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

        $to = implode( ';', $this->mobile );

        try {

            $client             = new SoapClient( "http://aradpayamak.net/APPs/SMS/WebService.php?wsdl" );
            $sendsms_parameters = [
                'domain'   => 'aradpayamak.net',
                'username' => $username,
                'password' => $password,
                'from'     => $from,
                'to'       => $to,
                'text'     => $massage,
                'isflash'  => 0,
            ];

            $sms_response = call_user_func_array( [ $client, 'sendSMS' ], $sendsms_parameters );

            if ( ! empty( $sms_response ) ) {
                return true; // Success
            }

        } catch ( SoapFault $ex ) {
            $sms_response = $ex->getMessage();
        }

        if ( $response !== true ) {
            $response = $sms_response;
        }

        return $response;
    }
}
