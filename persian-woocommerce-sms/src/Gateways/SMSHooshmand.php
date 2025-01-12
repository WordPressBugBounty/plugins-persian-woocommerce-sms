<?php

namespace PW\PWSMS\Gateways;


use nusoap_client;

class SMSHooshmand implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'smshooshmand';
    }

    public static function name() {
        return 'smshooshmand.com';
    }

    public function send() {
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = $this->mobile;

        $client = new nusoap_client( "http://smswbs.ir/class/sms/webservice/server.php?wsdl" );

        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8      = true;
        $client->setCredentials( $username, $password );

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

        $parameters = [
            'from'       => $from,
            'rcpt_array' => $to,
            'msg'        => $massage,
            'type'       => 'normal',
        ];

        $result = $client->call( "enqueue", $parameters );
        if ( ( isset( $result['state'] ) && $result['state'] == 'done' ) && ( isset( $result['errnum'] ) && ( $result['errnum'] == '100' || $result['errnum'] == 100 ) ) ) {
            return true; // Success
        } else {
            $response = $result;
        }

        return $response;
    }
}
