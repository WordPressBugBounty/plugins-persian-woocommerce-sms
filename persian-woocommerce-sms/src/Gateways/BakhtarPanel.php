<?php

namespace PW\PWSMS\Gateways;

use nusoap_client;
use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class BakhtarPanel implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'bakhtarpanel';
    }

    public static function name() {
        return 'bakhtar.xyz';
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

        $to = implode( ',', $this->mobile );

        /*PWSMS()->nusoap();*/

        $client = new nusoap_client( 'http://login.bakhtar.xyz/webservice/server.asmx?wsdl' );

        $status = explode( ',', ( $client->call( 'Sendsms', [
            '4',
            $from,
            $username,
            $password,
            '98',
            $massage,
            $to,
            false,
        ] ) ) );

        if ( count( $status ) > 1 && $status[0] == 1 ) {
            return true; // Success
        } else {
            $response = $status;
        }

        return $response;
    }
}
