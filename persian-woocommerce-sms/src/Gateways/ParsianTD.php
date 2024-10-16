<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class ParsianTD implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'parsiantd';
    }

    public static function name() {
        return 'sms.parsiantd.com';
    }

    public function send() {
        $username  = $this->username;
        $password  = $this->password;
        $from      = $this->senderNumber;
        $recievers = $this->mobile;
        $massage   = $this->message;
        if ( empty( $username ) || empty( $password ) ) {
            return false;
        }

        $errors = [];

        foreach ( (array) $recievers as $to ) {

            $content = 'http://sms.parsiantd.com/Api-Services/sms_sender_url.php?' .
                       '&username=' . rawurlencode( $username ) .
                       '&password=' . rawurlencode( $password ) .
                       '&from=' . rawurlencode( $from ) .
                       '&to=' . rawurlencode( $to ) .
                       '&text=' . $massage;

            $remote = wp_remote_get( $content );

            $sms_response = intval( wp_remote_retrieve_body( $remote ) );

            if ( $sms_response < 12 ) {
                $errors[ $to ] = $sms_response;
            }
        }

        if ( empty( $errors ) ) {
            return true; // Success
        } else {
            $response = $errors;
        }

        return $response;
    }
}
