<?php

namespace PW\PWSMS\Gateways;

use nusoap_client;
use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class SMSFor implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'smsfor';
    }

    public static function name() {
        return 'smsfor.ir';
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
            $to[ $i ] = '0' . $ret;
        }

        $client                   = new nusoap_client( 'http://www.smsfor.ir/webservice/soap/smsService.php?wsdl',
            'wsdl' );
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8      = false;

        $params = [
            'username'         => $username,
            'password'         => $password,
            'sender_number'    => [ $from ],
            'receiver_number'  => $to,
            'note'             => [ $massage ],
            'date'             => [],
            'request_uniqueid' => [],
            'flash'            => false,
            'onlysend'         => 'ok',
        ];
        $md_res = $client->call( "send_sms", $params );

        if ( empty( $md_res['getMessage()'] ) && empty( $md_res['getMessage()'] ) && is_numeric( str_ireplace( ',', '',
                $md_res[0] ) ) ) {
            return true; // Success
        } else {
            $response = $md_res;
        }

        return $response;
    }
}
