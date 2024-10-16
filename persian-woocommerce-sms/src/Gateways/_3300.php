<?php

namespace PW\PWSMS\Gateways;

use Exception;
use nusoap_client;
use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class _3300 implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return '_3300';
    }

    public static function name() {
        return 'sms.3300.ir';
    }

    public function send() {
        $response = false;
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;


        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = $this->mobile;
        /*PWSMS()->nusoap();*/

        try {
            $client                   = new nusoap_client( 'http://sms.3300.ir/almassms.asmx?wsdl', 'wsdl', '', '', '',
                '' );
            $client->soap_defencoding = 'UTF-8';
            $client->decode_utf8      = true;

            $param  = [
                'pUsername' => $username,
                'pPassword' => $password,
                'line'      => $from,
                'messages'  => [ 'string' => ( $massage ) ],
                'mobiles'   => [ 'string' => $to ],
                'Encodings' => [ 'int' => 2 ],
                'mclass'    => [ 'int' => 1 ],
            ];
            $result = $client->call( "Send", $param );
            $result = isset( $result['SendResult'] ) ? $result['SendResult'] : 0;

            if ( $result < 0 ) {
                return true; // Success
            } else {
                $response = $result;
            }

        } catch ( Exception $ex ) {
            $response = $ex->getMessage();
        }

        return $response;
    }
}
