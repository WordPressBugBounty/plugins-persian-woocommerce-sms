<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SepahanSMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'sepahansms';
    }

    public static function name() {
        return 'sepahansms.com (sepahangostar.com)';
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

            $client = new SoapClient( "http://www.sepahansms.com/smsSendWebServiceforphp.asmx?wsdl" );

            $sms_response = $client->SendSms( [
                'UserName'     => $username,
                'Pass'         => $password,
                'Domain'       => 'sepahansms',
                'SmsText'      => [ $massage ],
                'MobileNumber' => $to,
                'SenderNumber' => $from,
                'sendType'     => 'StaticText',
                'smsMode'      => 'SaveInPhone',
            ] )->SendSmsResult->long;

            if ( is_array( $sms_response ) || $sms_response > 1000 ) {
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
