<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class FirstPayamak implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'firstpayamak';
    }

    public static function name() {
        return 'firstpayamak.ir';
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

        try {
            $client = new SoapClient( "http://ui.firstpayamak.ir/webservice/v2.asmx?WSDL" );
            $params = [
                'username'         => $username,
                'password'         => $password,
                'recipientNumbers' => $to,
                'senderNumbers'    => [ $from ],
                'messageBodies'    => [ $massage ],
            ];

            $sms_response = $client->SendSMS( $params );
            $sms_response = (array) $sms_response->SendSMSResult->long;
        } catch ( SoapFault $ex ) {
            $sms_response = $ex->getMessage();
        }

        if ( is_array( $sms_response ) ) {
            foreach ( array_filter( $sms_response ) as $send ) {
                if ( $send > 1000 ) {
                    return true; // Success
                }
            }
        }

        if ( $response !== true ) {
            $response = $sms_response;
        }

        return $response;
    }
}
