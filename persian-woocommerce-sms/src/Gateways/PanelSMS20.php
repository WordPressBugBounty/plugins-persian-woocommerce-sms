<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class PanelSMS20 implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'panelsms20';
    }

    public static function name() {
        return 'panelsms20.ir';
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
            foreach ( $to as $key => $value ) {
                // $arr[3] will be updated with each value from $arr...

                $param    = [
                    'userName'    => $username,
                    'password'    => $password,
                    'msg'         => $massage,
                    'from'        => $from,
                    'to'          => $value,
                    'isFlashSend' =>
                        false,
                ];
                $client   = new SoapClient( "http://panelsms20.ir/services/SMSServices.asmx?WSDL" );
                $response = $client->Send( $param );
            }


            $sms_response = $response;
        } catch ( SoapFault $ex ) {
            $sms_response = $ex->getMessage();
        }

        if ( $sms_response == 1 ) {
            return true; // Success
        } else {
            $response = $sms_response;
        }

        return $response;
    }
}
