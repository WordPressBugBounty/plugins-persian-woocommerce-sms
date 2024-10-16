<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class WebOne implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'webone';
    }

    public static function name() {
        return 'webone-sms.com';
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
            $client       = new SoapClient( "http://payamakapi.ir/SendService.svc?wsdl" );
            $encoding     = "UTF-8";
            $parameters   = [
                'userName'       => $username,
                'password'       => $password,
                'fromNumber'     => $from,
                'toNumbers'      => $to,
                'messageContent' => iconv( $encoding, 'UTF-8//TRANSLIT', $massage ),
                'isflash'        => false,
                'udh'            => "",
                'recId'          => [ 0 ],
                'status'         => 0,
            ];
            $sms_response = $client->SendSms( $parameters )->SendSMSResult;

        } catch ( SoapFault $ex ) {
            $sms_response = $ex->getMessage();
        }

        if ( strval( $sms_response ) == '0' ) {
            return true; // Success
        } else {
            $response = $sms_response;
        }

        return $response;
    }
}
