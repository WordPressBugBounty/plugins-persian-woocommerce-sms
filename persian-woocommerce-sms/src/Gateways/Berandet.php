<?php

namespace PW\PWSMS\Gateways;

use nusoap_client;
use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class Berandet implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'berandet';
    }

    public static function name() {
        return 'berandet.ir';
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


        /*PWSMS()->nusoap();*/

        $i = sizeOf( $to );
        while ( $i -- ) {
            $uNumber = trim( $to[ $i ] );
            $ret     = &$uNumber;
            if ( substr( $uNumber, 0, 3 ) == '%2B' ) {
                $ret = substr( $uNumber, 3 );
            }
            if ( substr( $uNumber, 0, 3 ) == '%2b' ) {
                $ret = substr( $uNumber, 3 );
            }
            if ( substr( $uNumber, 0, 4 ) == '0098' ) {
                $ret = substr( $uNumber, 4 );
            }
            if ( substr( $uNumber, 0, 3 ) == '098' ) {
                $ret = substr( $uNumber, 3 );
            }
            if ( substr( $uNumber, 0, 3 ) == '+98' ) {
                $ret = substr( $uNumber, 3 );
            }
            if ( substr( $uNumber, 0, 2 ) == '98' ) {
                $ret = substr( $uNumber, 2 );
            }
            if ( substr( $uNumber, 0, 1 ) == '0' ) {
                $ret = substr( $uNumber, 1 );
            }
            $to[ $i ] = '+98' . $ret;
        }

        $timeout                  = 1800;
        $response_timeout         = 180;
        $client                   = new nusoap_client( "http://berandet.ir/Modules/DevelopmentTools/Groups/Messaging/MessagingWbs.php?wsdl",
            true, false, false, false, false, $timeout, $response_timeout, '' );
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8      = false;
        $client->response_timeout = $response_timeout;
        $client->timeout          = $timeout;

        $parameter = [
            'request' => [
                'username'   => $username,
                'password'   => $password,
                'fromNumber' => $from,
                'message'    => $massage,
                'recieptor'  => $to,
            ],
        ];

        $result = $client->call( 'sendMessageOneToMany', $parameter );
        $result = json_decode( $result, true );

        if ( ( isset( $result['errCode'] ) && $result['errCode'] < 0 ) || ! empty( $result['err'] ) ) {
            $response = $result;
        } else {
            return true; // Success
        }

        return $response;
    }
}
