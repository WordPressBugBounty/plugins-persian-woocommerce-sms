<?php

namespace PW\PWSMS\Gateways;

use PW\PWSMS\PWSMS;
use SoapClient;
use SoapFault;

class WebSMS implements GatewayInterface {
    use GatewayTrait;

    public static function id() {
        return 'websms';
    }

    public static function name() {
        return 's1.websms.ir';
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

        $content = 'cusername=' . rawurlencode( $username ) .
                   '&cpassword=' . rawurlencode( $password ) .
                   '&cmobileno=' . rawurlencode( $to ) .
                   '&csender=' . rawurlencode( $from ) .
                   '&cbody=' . $massage;

        $remote = wp_remote_get( 'http://s1.websms.ir/wservice.php?' . $content );

        $sms_response = wp_remote_retrieve_body( $remote );

        if ( strlen( $sms_response ) > 8 ) {
            return true; // Success
        } else {
            $response = $sms_response;
        }

        return $response;
    }
}
