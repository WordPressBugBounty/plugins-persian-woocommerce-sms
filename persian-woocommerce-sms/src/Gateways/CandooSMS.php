<?php

namespace PW\PWSMS\Gateways;


use nusoap_client;
use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class CandooSMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'candoo';
    }
    public static function name() {
        return 'CandooSMS.com';
    }

    public function send() {

        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;
        $to       = $this->mobile;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

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
            $to[ $i ] = '98' . $ret;
        }

        /*PWSMS()->nusoap();*/

        try {
            $client                   = new nusoap_client( 'http://my.candoosms.com/services/?wsdl', true );
            $client->soap_defencoding = 'UTF-8';
            $client->decode_utf8      = false;

            $results = $client->call( 'Send', [
                'username'  => $username,
                'password'  => $password,
                'srcNumber' => $from,
                'body'      => $massage,
                'destNo'    => $to,
                'flash'     => '0',
            ] );

            $error = [];
            foreach ( $results as $result ) {
                if ( ! isset( $result['Mobile'] ) || stripos( $result['ID'], 'e' ) !== false ) {
                    $error[] = $result;
                }
            }

            if ( empty( $error ) ) {
                return true; // Success
            }
        } catch ( Exception $e ) {
            $response = $e->getMessage();
        }

        return $response;
    }

}