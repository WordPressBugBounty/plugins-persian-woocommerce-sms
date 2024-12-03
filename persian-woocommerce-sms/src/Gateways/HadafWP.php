<?php

namespace PW\PWSMS\Gateways;


use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class HadafWP implements GatewayInterface {
    use GatewayTrait;

    public static function id(  ) {
        return 'hadafwp';
    }
    public static function name() {
        return 'sms.hadafwp.com';
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

            $client = new SoapClient( "http://sms.hadafwp.com/Post/Send.asmx?wsdl" );

            $encoding     = "UTF-8";
            $parameters   = [
                'username' => $username,
                'password' => $password,
                'from'     => $from,
                'to'       => $to,
                'text'     => iconv( $encoding, 'UTF-8//TRANSLIT', $massage ),
                'isflash'  => false,
                'udh'      => "",
                'recId'    => [ 0 ],
                'status'   => 0,
            ];
            $sms_response = $client->SendSms( $parameters )->SendSmsResult;
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