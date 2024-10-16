<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class ParsianSMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'parsiansms';
    }

    public static function name() {
        return 'parsian-sms.ir';
    }

    public function send() {
        $username = $this->username;
        $password = $this->password;
        $from     = $this->senderNumber;
        $massage  = $this->message;

        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $to = implode( ',', $this->mobile );

        $content = 'uname=' . rawurlencode( $username ) .
                   '&pass=' . rawurlencode( $password ) .
                   '&to=' . rawurlencode( $to ) .
                   '&from=' . rawurlencode( $from ) .
                   '&msg=' . $massage;

        $remote = wp_remote_get( 'http://185.4.31.182/class/sms/webservice/send_url.php?' . $content );

        $response = wp_remote_retrieve_body( $remote );

        if ( strtolower( $response ) == 'ok' || stripos( $response, 'done' ) !== false ) {
            return true; // Success
        }

        return $response;
    }
}
