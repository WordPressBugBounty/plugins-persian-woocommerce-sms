<?php

namespace PW\PWSMS\Gateways;


use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class TJPIR implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'tjp';
    }

    public static function name() {
        return 'TJP.ir';
    }

    public function send() {

        $username = $this->username;
        $password = $this->password;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        //$from     = $this->senderNumber;
        $to      = $this->mobile;
        $massage = $this->message;

        try {

            $client = new SoapClient( 'http://sms-login.tjp.ir/webservice/?WSDL', [
                'login'    => $username,
                'password' => $password,
            ] );

            $client->sendToMany( $to, $massage );

        } catch ( SoapFault $sf ) {
            $sms_response = $sf->getMessage();
        }

        if ( empty( $sms_response ) ) {
            return true; // Success
        } else {
            $response = $sms_response;
        }

        return $response;
    }

}